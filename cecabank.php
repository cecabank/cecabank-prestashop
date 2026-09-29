<?php
/**
 * Cecabank Module
 *
 * Copyright (c) 2019 Cecabank, S.A.
 *
 * @category  Payment
 * @author    Cecabank, S.A.
 * @copyright 2019, Cecabank, S.A.
 * @link      https://www.cecabank.es/
 * @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 *
 * Description:
 *
 * Plugin de Prestashop para conectar con la pasarela de Cecabank.
 *
 * --
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to tpv@cecabank.es so we can send you a copy immediately.
 */

if (!defined('_PS_VERSION_')) {
    exit();
}

$autoloader_param = dirname(__FILE__) . '/lib/Cecabank/Client.php';
try {
    require_once $autoloader_param;
} catch (\Exception $e) {
    throw new \Exception('Error en el plugin de Cecabank al cargar la librería.');
}

class Cecabank extends PaymentModule
{
    /**
     * Longitud de la clave secreta antigua, que firma con SHA2. Las claves nuevas
     * (HMAC) tienen 32 caracteres. Única regla para elegir el cifrado y para
     * mostrar el aviso de actualización de seguridad: ver isLegacySecretKey().
     */
    const LEGACY_SECRET_KEY_LENGTH = 8;

    /** Enlaces del aviso de actualización de seguridad */
    const SECURITY_PORTAL_URL = 'https://comercios.ceca.es/';
    const SECURITY_BULLETIN_URL = 'https://comercios.ceca.es/docs_constpv/seguridad/TPV_Virtual_Boletin_de_Seguridad_0525_001.pdf';
    /** URL del manual del plugin. Si se deja vacía, "Manual plugin" se muestra sin enlace. */
    const PLUGIN_MANUAL_URL = 'https://comercios.ceca.es/resourcesPortal/descargables_portal/manual_prestashop.pdf';

    /** Incrementar al añadir hooks a $this->hooks para que las tiendas ya instaladas los registren. */
    const HOOKS_VERSION = '2';

    /** Hooks que usa el módulo. registerMissingHooks() registra los que falten en instalaciones existentes. */
    protected $hooks = array(
        'payment',
        'paymentReturn',
        'paymentOptions',
        'displayAdminOrderContentOrder',
        'displayAdminOrderTabContent',
        'displayAdminOrderTabOrder',
        'displayAdminOrderTabLink',
        'displayBackOfficeHeader',
        // Aviso de seguridad: displayAdminAfterHeader cubre las páginas Symfony (y las legacy en PS 1.7/8);
        // displayDashboardTop cubre las páginas legacy de PS 8/9 (Escritorio incluido), cuyo layout no
        // renderiza displayAdminAfterHeader. Una marca estática evita mostrarlo dos veces.
        'displayAdminAfterHeader',
        'displayDashboardTop',
    );

    /** El aviso de clave antigua ya se ha mostrado en esta petición (getContent() o uno de los hooks) */
    protected static $secret_key_notice_shown = false;

    private $html = '';
    private $refund_status = 0;

    /**
     * Build module
     *
     * @see PaymentModule::__construct()
     */
    public function __construct()
    {
        $this->name = 'cecabank';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.5';
        $this->author = 'Cecabank, S.A.';
        $this->module_key = '6eb2e3f04585408d8cd6ad2f5a02e1af';
        $this->currencies = true;
        $this->currencies_mode = 'radio';
        $this->is_eu_compatible = 1;
        $this->controllers = array(
            'payment',
            'validation'
        );
        parent::__construct();
        $this->page = basename(__FILE__, '.php');
        $this->displayName = $this->l('Cecabank');
        $this->description = $this->l('Plugin de Prestashop para conectar con la pasarela de Cecabank.');
        $this->confirmUninstall = $this->l('¿Estás seguro que deseas eleminar tus detalles?');

        /* Add configuration warnings if needed */
        if (!Configuration::get('merchant')
            || !Configuration::get('acquirer')
            || !Configuration::get('secret_key')
            || !Configuration::get('terminal')
            || !Configuration::get('environment')
            || !Configuration::get('title')
            || !Configuration::get('description')
            || !Configuration::get('icon')) {
            $this->warning = $this->l('Module configuration is incomplete.');
        }
        $this->registerHook('displayAdminOrderContentOrder');
        $this->registerHook('displayAdminOrderTabContent');
        $this->registerHook('displayAdminOrderTabOrder');
        $this->registerHook('displayAdminOrderTabLink');
        $this->registerHook('displayBackOfficeHeader');
    }

    /**
     * Install module
     *
     * @see PaymentModule::install()
     */
    public function install()
    {
        if (!parent::install()
            || !Configuration::updateValue('merchant', '')
            || !Configuration::updateValue('acquirer', '')
            || !Configuration::updateValue('secret_key', '')
            || !Configuration::updateValue('terminal', '')
            || !Configuration::updateValue('environment', 'test')
            || !Configuration::updateValue('title', 'Tarjeta')
            || !Configuration::updateValue('description', 'Paga con tu tarjeta')
            || !Configuration::updateValue('icon', 'https://pgw.ceca.es/TPVvirtual/images/logo0000554000.gif')
            || !$this->registerHooks()) {
            return false;
        }
        return true;
    }

    /**
     * Registra los hooks de $this->hooks que falten y anota la versión de la lista.
     *
     * @return bool
     */
    protected function registerHooks()
    {
        foreach ($this->hooks as $hook) {
            if (!$this->isHookRegistered($hook) && !$this->registerHook($hook)) {
                return false;
            }
        }

        return Configuration::updateValue('CECABANK_HOOKS_VERSION', self::HOOKS_VERSION);
    }

    /**
     * Indica si el módulo ya está registrado en un hook.
     *
     * Además de isRegisteredInHook(), que resuelve alias (paymentReturn -> displayPaymentReturn),
     * comprueba el nombre literal: en PrestaShop 8/9 las instalaciones antiguas quedan
     * registradas en la fila del alias y registerHook() volvería a insertarla, provocando
     * un error de clave duplicada.
     *
     * @param string $hook
     *
     * @return bool
     */
    protected function isHookRegistered($hook)
    {
        if ($this->isRegisteredInHook($hook)) {
            return true;
        }

        return (bool) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'hook_module` hm
            INNER JOIN `' . _DB_PREFIX_ . 'hook` h ON h.`id_hook` = hm.`id_hook`
            WHERE hm.`id_module` = ' . (int) $this->id . ' AND h.`name` = \'' . pSQL($hook) . '\''
        );
    }

    /**
     * Registra los hooks que falten en tiendas que ya tenían el módulo instalado.
     *
     * Los hooks nuevos (por ejemplo displayAdminAfterHeader) no se registran solos
     * al actualizar los ficheros del módulo. Se ejecuta en cada página del back
     * office (hookBackOfficeHeader) y solo hace trabajo cuando HOOKS_VERSION cambia.
     * Alternativa manual: "Reiniciar" el módulo desde el gestor de módulos.
     *
     * @return bool
     */
    public function registerMissingHooks()
    {
        if (!$this->id || Configuration::get('CECABANK_HOOKS_VERSION') === self::HOOKS_VERSION) {
            return true;
        }

        return $this->registerHooks();
    }

    /**
     * Uninstall module
     *
     * @see PaymentModule::uninstall()
     */
    public function uninstall()
    {
        if (!Configuration::deleteByName('merchant')
            || !Configuration::deleteByName('acquirer')
            || !Configuration::deleteByName('secret_key')
            || !Configuration::deleteByName('terminal')
            || !Configuration::deleteByName('environment')
            || !Configuration::deleteByName('title')
            || !Configuration::deleteByName('description')
            || !Configuration::deleteByName('icon')
            || !Configuration::deleteByName('CECABANK_HOOKS_VERSION')
            || !parent::uninstall()) {
            return false;
        }
        return true;
    }

    /**
     * Validate submited data
     */
    private function postValidation()
    {
        $this->_errors = array();
        if (Tools::getValue('submitUpdate')) {
            if (!Tools::getValue('merchant')) {
                $this->_errors[] = $this->l('cecabank "merchant" is required.');
            }
            if (!Tools::getValue('acquirer')) {
                $this->_errors[] = $this->l('cecabank "acquirer" is required.');
            }
            if (!Tools::getValue('secret_key')) {
                $this->_errors[] = $this->l('cecabank "secret_key" is required.');
            }
            if (!Tools::getValue('terminal')) {
                $this->_errors[] = $this->l('cecabank "terminal" is required.');
            }
            if (!Tools::getValue('environment')) {
                $this->_errors[] = $this->l('cecabank "environment" is required.');
            }
            if (!Tools::getValue('title')) {
                $this->_errors[] = $this->l('cecabank "title" is required.');
            }
            if (!Tools::getValue('description')) {
                $this->_errors[] = $this->l('cecabank "description" is required.');
            }
            if (!Tools::getValue('icon')) {
                $this->_errors[] = $this->l('cecabank "icon" is required.');
            }
        }
    }

    /**
     * Update submited configurations
     */
    public function getContent()
    {
        $this->html = '<h2>' . $this->displayName . '</h2>';
        if (Tools::isSubmit('submitUpdate')) {
            Configuration::updateValue('merchant', Tools::getValue('merchant'));
            Configuration::updateValue('acquirer', Tools::getValue('acquirer'));
            Configuration::updateValue('secret_key', Tools::getValue('secret_key'));
            Configuration::updateValue('terminal', Tools::getValue('terminal'));
            Configuration::updateValue('environment', Tools::getValue('environment'));
            Configuration::updateValue('title', Tools::getValue('title'));
            Configuration::updateValue('description', Tools::getValue('description'));
            $icon = Tools::getValue('icon');
            $acquirer = Tools::getValue('acquirer');
            if (strpos($icon, 'assets/images/icons/cecabank.png') !== false || 
                ($acquirer && $acquirer !== '0000554000' && $icon === "https://pgw.ceca.es/TPVvirtual/images/logo0000554000.gif") ) {
                Configuration::updateValue('icon', "https://pgw.ceca.es/TPVvirtual/images/logo".$acquirer.".gif");
            } else {
                Configuration::updateValue('icon', Tools::getValue('icon'));
            }
        }

        // Aviso de actualización de seguridad: clave antigua de 8 caracteres en la tienda del contexto
        if (self::isLegacySecretKey(Configuration::get('secret_key'))) {
            $this->html .= $this->displayWarning($this->getSecretKeyNoticeHtml());
            self::$secret_key_notice_shown = true;
        }

        $this->postValidation();
        if (isset($this->_errors) && count($this->_errors)) {
            foreach ($this->_errors as $err) {
                $this->html .= $this->displayError($err);
            }
        } elseif (Tools::getValue('submitUpdate') && !count($this->_errors)) {
            $this->html .= $this->displayConfirmation($this->l('Configuración actualizada'));
        }

        return $this->html . $this->displayForm();
    }

    /**
     * Build and display admin form for configurations
     */
    private function displayForm()
    {
        $dfl = array(
            'action' => $_SERVER['REQUEST_URI'],
            'img_path' => $this->_path . 'views/img/icons/cecabank.png',
            'path' => $this->_path,
            'notification_url' => $this->getNotificationUrl()
        );

        $config = Configuration::getMultiple(array(
            'merchant',
            'acquirer',
            'secret_key',
            'terminal',
            'environment',
            'title',
            'description',
            'icon'
        ));

        $this->context->smarty->assign(array(
            'cecabank' => array(
                'dfl' => $dfl,
                'config' => $config
            )
        ));

        return $this->display(__FILE__, 'views/templates/admin/display_form.tpl');
    }

    /**
     * Build and display payment button
     *
     * @param unknown $params
     * @return boolean|\PrestaShop\PrestaShop\Core\Payment\PaymentOption[]
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->isPayment()) {
            return false;
        }

        $this->context->smarty->assign('path', $this->_path);
        $this->context->smarty->assign('title', Configuration::get('title'));
        $this->context->smarty->assign('description', Configuration::get('description'));
        $this->context->smarty->assign('acquirer', Configuration::get('acquirer'));
        $this->context->smarty->assign('icon', Configuration::get('icon'));

        $paymentOption = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $paymentOption->setCallToActionText(Configuration::get('title'))
            ->setAction($this->context->link->getModuleLink($this->name, 'payment', array(
                'token' => Tools::getToken(false),
                'random' => rand()
            ), true))
            ->setAdditionalInformation($this->context->smarty->fetch(
                'module:cecabank/views/templates/hook/payment_options.tpl'
            ));

        return array($paymentOption);
    }

    /**
     * Build and display payment button
     *
     * @param array $params
     * @return string Templatepart
     */
    public function hookPayment($params)
    {
        if (!$this->isPayment()) {
            return false;
        }

        $this->context->smarty->assign('path', $this->_path);
        $this->context->smarty->assign('static_token', Tools::getToken(false));
        $this->context->smarty->assign('array_token', array('token' => Tools::getToken(false), 'random' => rand()));
        $this->context->smarty->assign('title', Configuration::get('title'));
        $this->context->smarty->assign('description', Configuration::get('description'));
        $this->context->smarty->assign('acquirer', Configuration::get('acquirer'));
        $this->context->smarty->assign('icon', Configuration::get('icon'));

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    /**
     * Build and display confirmation
     *
     * @param array $params
     * @return string Templatepart
     */
    public function hookPaymentReturn($params)
    {
        if (!$this->isPayment()) {
            return false;
        }

        $this->context->smarty->assign('path', $this->_path);

        /* If PS version is >= 9.0 */
        $currency = new Currency($params['order']->id_currency);
        if (version_compare(_PS_VERSION_, '9.0', '>=')) {
            $this->context->smarty->assign(array(
                'amount' => $this->context->getCurrentLocale()->formatPrice(
                    $params['order']->getOrdersTotalPaid(),
                    $currency->iso_code
                )
            ));
        } else if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            /* If PS version is >= 1.7 */
            $this->context->smarty->assign(array(
                'amount' => Tools::displayPrice(
                    $params['order']->getOrdersTotalPaid(),
                    $currency,
                    false
                )
            ));
        } else {
            $this->context->smarty->assign(array(
                'amount' => Tools::displayPrice(
                    $params['total_to_pay'],
                    $params['currencyObj'],
                    false
                )
            ));
        }

        $this->context->smarty->assign('shop_name', $this->context->shop->name);

        return $this->display(__FILE__, 'views/templates/hook/payment_return.tpl');
    }

    /**
     * Check if payment is active
     *
     * @return boolean
     */
    public function isPayment()
    {
        if (!$this->active) {
            return false;
        }

        if (!Configuration::get('merchant')
            || !Configuration::get('acquirer')
            || !Configuration::get('secret_key')
            || !Configuration::get('terminal')
            || !Configuration::get('environment')
            || !Configuration::get('title')
            || !Configuration::get('description')
            || !Configuration::get('icon')) {
            return false;
        }

        return true;
    }

    public function hookBackOfficeHeader()
    {
        // Registra los hooks nuevos en tiendas que ya tenían el módulo instalado
        $this->registerMissingHooks();

        $this->refund_status = 0;
        if (!isset($_POST['id_order']) || !isset($_POST['pr']) || !isset($_POST['cecabank_refund_token'])) {
           return;
        }

        if (!$this->context->employee || !$this->context->employee->isLoggedBack()) {
            return;
        }

        $order_id = (int) Tools::getValue('id_order');
        $expected_token = $this->getRefundCsrfToken($order_id);
        $received_token = (string) Tools::getValue('cecabank_refund_token');
        if (!hash_equals($expected_token, $received_token)) {
            return;
        }

        $order = new Order($order_id);
        $number = str_replace(',', '.', Tools::getValue('pr'));
        $orderPayments = $order->getOrderPaymentCollection();
        $paid = 0;
        foreach ($orderPayments as $orderPay) {
            $paid += (float) $orderPay->amount;
        }
        if ($paid < (float) number_format($number, 2)) {
            $this->refund_status = 3;
            return;
        }
        $orderPayment = $orderPayments[0];
        $transaction_id = $orderPayment->transaction_id;

        $config = $this-> get_client_config(); 
        $cecabank_client = new Cecabank\Client($config);
        $currency = new Currency($order->id_currency);

        $refund_data = array(
            'Num_operacion' => $order->id_cart,
            'Referencia' => $transaction_id,
            'Importe' => number_format($number, 2),
            'TIPO_ANU' => 'P',
            'TipoMoneda' => $cecabank_client->getCurrencyCode($currency->iso_code)
        );
        if ($cecabank_client->refund($refund_data)) {
            $this->refund_status = 1;
            $orderPayment->amount -= (float) number_format($number, 2);
            // $orderPayment->id = 0;
            $orderPayment->save();
        } else {
            $this->refund_status = 2;
        }
    }

    protected function getRefundCsrfToken($order_id)
    {
        $employee_id = $this->context->employee ? (int) $this->context->employee->id : 0;
        $data = 'cecabank-refund-' . (int) $order_id . '-' . $employee_id;
        // Tools::encrypt() fue eliminado en PrestaShop 9; Tools::hash() existe desde 1.7
        if (method_exists('Tools', 'hash')) {
            return Tools::hash($data);
        }
        return Tools::encrypt($data);
    }

    protected function get_client_config() {
        $secret_key = Configuration::get('secret_key');
        $cifrado = self::getCifradoForSecretKey($secret_key);
        return array(
            'Environment' => Configuration::get('environment'),
            'MerchantID' => Configuration::get('merchant'),
            'AcquirerBIN' => Configuration::get('acquirer'),
            'TerminalID' => Configuration::get('terminal'),
            'ClaveCifrado' => $secret_key,
            'Exponente' => '2',
            'Cifrado' => $cifrado,
            'Idioma' => '1',
            'Pago_soportado' => 'SSL',
            'versionMod' => 'P-'.$this->version
        );
    }

    /**
     * Indica si la clave secreta es la antigua de 8 caracteres (firma SHA2).
     *
     * Es la única comprobación de longitud de clave del módulo: la usan la
     * elección del cifrado (getCifradoForSecretKey) y el aviso de seguridad.
     *
     * @param string $secret_key
     *
     * @return bool
     */
    public static function isLegacySecretKey($secret_key)
    {
        return strlen((string) $secret_key) === self::LEGACY_SECRET_KEY_LENGTH;
    }

    /**
     * Cifrado que corresponde a la clave configurada: SHA2 para la clave antigua
     * de 8 caracteres, HMAC para las nuevas.
     *
     * @param string $secret_key
     *
     * @return string
     */
    public static function getCifradoForSecretKey($secret_key)
    {
        return self::isLegacySecretKey($secret_key) ? 'SHA2' : 'HMAC';
    }

    /**
     * Aviso global del back office en las páginas Symfony (PrestaShop 1.7 a 9) y en las
     * páginas legacy de PrestaShop 1.7/8, cuando alguna tienda sigue configurada con la
     * clave antigua de 8 caracteres.
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayAdminAfterHeader($params)
    {
        return $this->renderSecretKeyNoticeOnce();
    }

    /**
     * Mismo aviso para las páginas legacy de PrestaShop 8/9 (Escritorio incluido), cuyo
     * layout no renderiza displayAdminAfterHeader pero sí displayDashboardTop.
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayDashboardTop($params)
    {
        return $this->renderSecretKeyNoticeOnce();
    }

    /**
     * Devuelve el aviso global una sola vez por petición. Depende solo de la longitud
     * de la clave, no de que el método de pago esté activo o configurado por completo.
     * En la página de configuración del módulo no se repite: getContent() ya lo muestra.
     *
     * @return string
     */
    protected function renderSecretKeyNoticeOnce()
    {
        if (self::$secret_key_notice_shown) {
            return '';
        }

        $shops = $this->getShopsWithLegacySecretKey();
        if (!count($shops)) {
            return '';
        }
        self::$secret_key_notice_shown = true;

        return $this->displayWarning($this->getSecretKeyNoticeHtml(Shop::isFeatureActive() ? $shops : array()));
    }

    /**
     * Nombres de las tiendas activas cuya clave secreta sigue siendo la antigua de 8 caracteres.
     *
     * @return array
     */
    protected function getShopsWithLegacySecretKey()
    {
        $shops = array();
        foreach (Shop::getShops(true) as $shop) {
            $secret_key = Configuration::get('secret_key', null, (int) $shop['id_shop_group'], (int) $shop['id_shop']);
            if (self::isLegacySecretKey($secret_key)) {
                $shops[] = $shop['name'];
            }
        }

        return $shops;
    }

    /**
     * HTML del aviso de actualización de seguridad (clave antigua de 8 caracteres).
     *
     * Los textos pasan por $this->l(), que escapa HTML, así que los enlaces se
     * insertan después con marcadores de sprintf.
     *
     * @param array $shop_names Tiendas afectadas; solo se listan en multitienda
     *
     * @return string
     */
    protected function getSecretKeyNoticeHtml(array $shop_names = array())
    {
        $close = '</a>';
        $manual = self::PLUGIN_MANUAL_URL !== ''
            ? $this->getNoticeLinkOpen(self::PLUGIN_MANUAL_URL) . $this->l('Manual plugin') . $close
            : $this->l('Manual plugin');

        $html = '<p><strong>' . $this->l('Acción requerida: actualización de seguridad pendiente') . '</strong></p>';
        $html .= '<p>' . $this->l('Tu comercio está configurado con una clave de 8 caracteres. Para completar la adaptación de seguridad debes:') . '</p>';
        // Estilo inline: el tema legacy del back office quita las viñetas de las listas dentro de las alertas
        $html .= '<ul style="list-style:disc;padding-left:20px;margin:5px 0;">';
        $html .= '<li>' . sprintf(
            $this->l('Configurar la nueva clave de 32 caracteres que puedes encontrar en el %1$sPortal de Administración del TPV Virtual%2$s, en la configuración de tu comercio.'),
            $this->getNoticeLinkOpen(self::SECURITY_PORTAL_URL),
            $close
        ) . '</li>';
        $html .= '<li>📖 ' . sprintf(
            $this->l('Más información: %1$sBoletín de Seguridad%2$s · %3$s'),
            $this->getNoticeLinkOpen(self::SECURITY_BULLETIN_URL),
            $close,
            $manual
        ) . '</li>';
        $html .= '</ul>';
        $html .= '<p>' . $this->l('Si ya has completado estas actualizaciones, puedes ignorar este mensaje.') . '</p>';
        if (count($shop_names)) {
            $html .= '<p>' . sprintf(
                $this->l('Tiendas afectadas: %s'),
                htmlspecialchars(implode(', ', $shop_names), ENT_QUOTES, 'UTF-8')
            ) . '</p>';
        }

        return $html;
    }

    /**
     * Apertura de un enlace del aviso, en pestaña nueva y sin acceso a window.opener.
     *
     * @param string $url
     *
     * @return string
     */
    protected function getNoticeLinkOpen($url)
    {
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">';
    }

    /**
     * URL de comunicación online que debe configurarse en la consola de Cecabank.
     *
     * Apunta al controlador front "validation", que pasa por el Dispatcher de
     * PrestaShop y por tanto no lo bloquea el modules/.htaccess de PrestaShop 9.
     *
     * @return string
     */
    public function getNotificationUrl()
    {
        return $this->context->link->getModuleLink($this->name, 'validation', array(), true);
    }

    /**
     * Configuración del cliente para verificar la comunicación online.
     *
     * La firma de la notificación se verifica siempre con SHA2, sea cual sea la
     * longitud de la clave secreta (corrección introducida en la versión 1.1.2).
     *
     * @return array
     */
    public function getNotificationClientConfig()
    {
        $config = $this->get_client_config();
        $config['Cifrado'] = 'SHA2';
        return $config;
    }

    /**
     * Procesa la comunicación online de Cecabank y registra el pedido.
     *
     * Lo usan tanto el controlador front "validation" como el fichero legado
     * validation.php de la raíz del módulo.
     *
     * @param array $post Datos POST enviados por la pasarela
     *
     * @return string Código de éxito que espera la pasarela
     *
     * @throws Exception Si la notificación no es válida o el pedido no puede validarse
     */
    public function processNotification(array $post)
    {
        $cecabank_client = new Cecabank\Client($this->getNotificationClientConfig());
        $cecabank_client->checkTransaction($post);

        $cart_id = (int) $post['Num_operacion'];
        $cart = new Cart($cart_id);
        if (!Validate::isLoadedObject($cart)) {
            throw new Exception(sprintf('Unable to load cart by cart id "%d".', $cart_id));
        }

        $customer = new Customer((int) $cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            throw new Exception(sprintf('Invalid or missing customer for cart id "%d".', $cart_id));
        }

        // Notificación reenviada: el pedido ya está registrado
        if ($this->hasValidOrder($cart->id)) {
            return $cecabank_client->successCode();
        }

        $reference = (string) $post['Referencia'];
        $amount = ((int) $post['Importe']) / 100;

        try {
            $validated = $this->validateOrder(
                (int) $cart->id,
                (int) Configuration::get('PS_OS_PAYMENT'),
                $amount,
                $this->displayName,
                $this->l(sprintf('Cecabank transaction ID: %s.', $reference)),
                array('transaction_id' => $reference),
                null,
                false,
                $customer->secure_key
            );
        } catch (Exception $e) {
            // Dos notificaciones simultáneas: la otra puede haber creado ya el pedido
            if ($this->hasValidOrder($cart->id)) {
                return $cecabank_client->successCode();
            }
            throw $e;
        }

        if (!$validated) {
            throw new Exception(sprintf('Unable to validate order for cart id "%d".', $cart_id));
        }

        return $cecabank_client->successCode();
    }

    /**
     * Registra un error de la comunicación online en los logs de PrestaShop
     * (Parámetros avanzados > Registros). Nunca interrumpe el flujo.
     *
     * @param string $message
     */
    public function logNotificationError($message)
    {
        if (!class_exists('PrestaShopLogger')) {
            return;
        }
        try {
            $message = substr(strip_tags((string) $message), 0, 500);
            PrestaShopLogger::addLog('Cecabank notification error: ' . $message, 3, null, 'Cecabank');
        } catch (Exception $e) {
            // Ignorado a propósito: un fallo al registrar no debe afectar a la respuesta
        }
    }

    /**
     * Comprueba si ya existe un pedido válido para el carrito.
     *
     * @param int $cart_id
     *
     * @return bool
     */
    protected function hasValidOrder($cart_id)
    {
        if (method_exists('Order', 'getIdByCartId')) {
            $order_id = (int) Order::getIdByCartId((int) $cart_id);
        } else {
            // Order::getIdByCartId existe desde PrestaShop 1.7.1
            $order_id = (int) Order::getOrderByCartId((int) $cart_id);
        }
        if (!$order_id) {
            return false;
        }
        $order = new Order($order_id);

        return Validate::isLoadedObject($order) && (bool) $order->valid;
    }

    public function hookDisplayAdminOrderContentOrder($params)
    {
        $order_id = $params['order']->id;
        $this->smarty->assign(array(
            'url_refund' => '',
            'order_id' => $order_id,
            'cecabank_refund_token' => $this->getRefundCsrfToken($order_id),
        ));
        return $this->display(__FILE__, 'views/templates/admin/order-content.tpl');
    }

    public function hookDisplayAdminOrderTabContent($params)
    {
        $order_id = $params['id_order'];
        $this->smarty->assign(array(
            'url_refund' => '',
            'order_id' => $order_id,
            'cecabank_refund_token' => $this->getRefundCsrfToken($order_id),
        ));
        return $this->display(__FILE__, 'views/templates/admin/order-tab-content.tpl');
    }

    public function hookDisplayAdminOrderTabOrder($params)
    {
        $this->smarty->assign(array(
            'ok' => $this->refund_status // isset($_POST['pr'])
        ));
        return $this->display(__FILE__, 'views/templates/admin/order-tab.tpl');
    }

    public function hookDisplayAdminOrderTabLink($params)
    {
        $this->smarty->assign(array(
            'ok' => $this->refund_status // isset($_POST['pr'])
        ));
        return $this->display(__FILE__, 'views/templates/admin/order-tab-link.tpl');
    }
}
