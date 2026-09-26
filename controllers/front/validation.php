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

/**
 * Controlador de la comunicación online (notificación servidor a servidor).
 *
 * URL: {tienda}/index.php?fc=module&module=cecabank&controller=validation
 * (o {tienda}/module/cecabank/validation con URLs amigables). Es la URL que
 * hay que configurar como "URL de comunicación online" en la consola de
 * Cecabank; se muestra en la página de configuración del módulo.
 *
 * PrestaShop 9 bloquea el acceso directo a los ficheros .php de /modules
 * mediante modules/.htaccess, por lo que la antigua URL
 * {tienda}/modules/cecabank/validation.php deja de funcionar. Este
 * controlador pasa por el Dispatcher de PrestaShop y no se ve afectado.
 */
class CecabankValidationModuleFrontController extends ModuleFrontController
{
    /** Cecabank notifica por HTTPS; así la URL generada usa el dominio SSL de la tienda. */
    public $ssl = true;

    /**
     * Recibe la notificación de la pasarela, verifica la firma y registra el pedido.
     *
     * @see FrontController::postProcess()
     */
    public function postProcess()
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST)) {
            $this->respond(400, 'Invalid notification, nothing todo.');
        }

        try {
            $result = $this->module->processNotification($_POST);
        } catch (Exception $e) {
            $this->module->logNotificationError($e->getMessage());
            $this->respond(400, 'Invalid notification, nothing todo.');
        }

        $this->respond(200, $result);
    }

    /**
     * Devuelve una respuesta en texto plano y termina la ejecución.
     *
     * @param int $status Código HTTP
     * @param string $body Cuerpo de la respuesta
     */
    private function respond($status, $body)
    {
        http_response_code((int) $status);
        header('Content-Type: text/plain; charset=utf-8');
        die($body);
    }
}
