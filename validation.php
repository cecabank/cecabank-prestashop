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

require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../init.php';
require_once dirname(__FILE__) . '/cecabank.php';
require_once dirname(__FILE__) . '/lib/Cecabank/Client.php';

function get_client_config() {
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

$config = get_client_config();
$cecabank_client = new Cecabank\Client($config);

try {
    $cecabank_client->checkTransaction($_POST);
} catch (\Exception $e) {
    throw new \Exception('Invalid notification, nothing todo.');
}

$cart_id = Tools::getValue('Num_operacion');

try {
    if ((!$cart = new Cart((int) $cart_id)) || !is_object($cart) || $cart->id === null) {
        throw new \Exception(sprintf('Unable to load cart by card id "%d".', $cart_id));
    }
    if ((!$customer = new Customer($cart->id_customer))) {
        throw new \Exception('Invalid or missing customer secure key for this transaction.');
    }
} catch (\Exception $e) {
    throw $e;
}

$reference = Tools::getValue('Referencia');
$importe = Tools::getValue('Importe');
$cecabank = new Cecabank();

$cecabank->validateOrder(
    $cart->id,
    _PS_OS_PAYMENT_,
    ((int) $importe) / 100,
    $cecabank->displayName,
    $cecabank->l(sprintf('Cecabank transaction ID: %s.', $reference)),
    array('transaction_id' => $reference),
    null,
    false,
    $customer->secure_key,
    null
);

die($cecabank_client->successCode());
