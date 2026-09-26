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

/*
 * Endpoint LEGADO de la comunicación online:
 *   {tienda}/modules/cecabank/validation.php
 *
 * Se mantiene para los comercios en PrestaShop < 9 que ya tienen esta URL
 * configurada en la consola de Cecabank.
 *
 * PrestaShop 9 bloquea el acceso directo a los ficheros .php de /modules
 * mediante modules/.htaccess (responde 403), por lo que en PrestaShop >= 9
 * hay que configurar la URL del controlador front "validation", que se
 * muestra en la página de configuración del módulo:
 *   {tienda}/index.php?fc=module&module=cecabank&controller=validation
 */

require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once dirname(__FILE__) . '/cecabank.php';

$cecabank = new Cecabank();

try {
    $result = $cecabank->processNotification($_POST);
} catch (\Exception $e) {
    $cecabank->logNotificationError($e->getMessage());
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    die('Invalid notification, nothing todo.');
}

header('Content-Type: text/plain; charset=utf-8');
die($result);
