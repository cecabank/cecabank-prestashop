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
        $this->version = '1.0.3';
        $this->author = 'Cecabank, S.A.';
        $this->module_key = '26f33953dc3c6c678b10fb0314dc92b2';
        $this->currencies = true;
        $this->currencies_mode = 'radio';
        $this->is_eu_compatible = 1;
        $this->controllers = array(
            'payment'
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
            || !Configuration::get('currency')
            || !Configuration::get('icon')) {
            $this->warning = $this->l('Module configuration is incomplete.');
        }
        $this->registerHook('displayAdminOrderContentOrder');
        $this->registerHook('displayAdminOrderTabOrder');
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
            || !Configuration::updateValue('currency', '978')
            || !Configuration::updateValue('icon', 'https://pgw.ceca.es/TPVvirtual/images/logo0000554000.gif')
            || !$this->registerHook('payment')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('displayAdminOrderContentOrder')
            || !$this->registerHook('displayAdminOrderTabOrder')
            || !$this->registerHook('displayBackOfficeHeader')) {
            return false;
        }
        return true;
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
            || !Configuration::deleteByName('currency')
            || !Configuration::deleteByName('icon')
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
            if (!Tools::getValue('currency')) {
                $this->_errors[] = $this->l('cecabank "currency" is required.');
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
            Configuration::updateValue('currency', Tools::getValue('currency'));
            $icon = Tools::getValue('icon');
            $acquirer = Tools::getValue('acquirer');
            if (strpos($icon, 'assets/images/icons/cecabank.png') !== false || 
                ($acquirer && $acquirer !== '0000554000' && $icon === "https://pgw.ceca.es/TPVvirtual/images/logo0000554000.gif") ) {
                Configuration::updateValue('icon', "https://pgw.ceca.es/TPVvirtual/images/logo".$acquirer.".gif");
            } else {
                Configuration::updateValue('icon', Tools::getValue('icon'));
            }
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
            'path' => $this->_path
        );

        $config = Configuration::getMultiple(array(
            'merchant',
            'acquirer',
            'secret_key',
            'terminal',
            'environment',
            'title',
            'description',
            'currency',
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
                'token' => Tools::getToken(false)
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
        $this->context->smarty->assign('array_token', array('token' => Tools::getToken(false)));
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

        /* If PS version is >= 1.7 */
        if (version_compare(_PS_VERSION_, '1.7', '>=')) {
            $this->context->smarty->assign(array(
                'amount' => Tools::displayPrice(
                    $params['order']->getOrdersTotalPaid(),
                    new Currency($params['order']->id_currency),
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
            || !Configuration::get('currency')
            || !Configuration::get('icon')) {
            return false;
        }

        return true;
    }

    public function hookBackOfficeHeader()
    {
        $this->refund_status = 0;
        if (!isset($_GET['id_order'])  || !isset($_POST['pr'])) {
            return;
        }

        $order = new Order($_GET['id_order']);
        $number = str_replace(',', '.', $_POST['pr']);
        $orderPayments = OrderPayment::getByOrderId($_GET['id_order']);
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
        $refund_data = array(
            'Num_operacion' => $order->id_cart,
            'Referencia' => $transaction_id,
            'Importe' => number_format($number, 2),
            'TIPO_ANU' => 'P',
        );

        $config = $this-> get_client_config(); 
        $cecabank_client = new Cecabank\Client($config);
        if ($cecabank_client->refund($refund_data)) {
            $this->refund_status = 1;
            $orderPayment->amount = '-'.number_format($number, 2);
            $orderPayment->id = 0;
            $orderPayment->save();
        } else {
            $this->refund_status = 2;
        }
    }

    protected function get_client_config() {
        return array(
            'Environment' => Configuration::get('environment'),
            'MerchantID' => Configuration::get('merchant'),
            'AcquirerBIN' => Configuration::get('acquirer'),
            'TerminalID' => Configuration::get('terminal'),
            'ClaveCifrado' => Configuration::get('secret_key'),
            'TipoMoneda' => Configuration::get('currency'),
            'Exponente' => '2',
            'Cifrado' => 'SHA2',
            'Idioma' => '1',
            'Pago_soportado' => 'SSL'
        );
    }

    public function hookDisplayAdminOrderContentOrder($params)
    {
        $order_id = $params['order']->id;
        $this->smarty->assign(array(
            'url_refund' => '', 
            'order_id' => $order_id,
        ));
        return $this->display(__FILE__, 'views/templates/admin/order-content.tpl');
    }

    public function hookDisplayAdminOrderTabOrder($params)
    {
        $this->smarty->assign(array(
            'ok' => $this->refund_status // isset($_POST['pr'])
        ));
        return $this->display(__FILE__, 'views/templates/admin/order-tab.tpl');
    }
}
