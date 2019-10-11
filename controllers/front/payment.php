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

require_once dirname(__FILE__) . '/../../lib/Cecabank/Client.php';

class CecabankPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    /**
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        $this->display_column_left = false;
        $this->display_column_right = false;

        parent::initContent();

        if (!$this->module->isPayment()) {
            throw new \Exception(sprintf(
                '%s Error: (Inactive or incomplete module configuration)',
                $this->module->displayName
            ));
        }

        $cart = $this->context->cart;
        $customer = new Customer((int) $cart->id_customer);
        $currency = $this->context->currency;
        $language = $this->context->language;

        if (!Validate::isLoadedObject($customer)
            || !Validate::isLoadedObject($currency)
            || !Validate::isLoadedObject($language)) {
            throw new \Exception(sprintf(
                '%s Error: (Invalid customer, language or currency object)',
                $this->module->displayName
            ));
        }

        $baseUrl = $this->context->shop->getBaseURL() . 'modules/' . $this->module->name;
        $url = array(
            'notify' => $baseUrl . '/validation.php',
            'cancel' => $this->context->shop->getBaseURL() . 'index.php?controller=order&step=3'
        );

        if (_PS_VERSION_ <= '1.5') {
            $url['return'] = $this->context->shop->getBaseURL() . 'index.php?controller=order-confirmation&id_cart='.
                $cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.
                $customer->secure_key;
        } else {
            $url['return'] = $this->context->shop->getBaseURL() . 'index.php?controller=order-confirmation&id_cart='.
                $cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.
                $customer->secure_key;
        }

        $config = $this-> get_client_config();

        $cecabank_client = new Cecabank\Client($config);

        $address = new Address((int)$cart->id_address_delivery);
        $address_invoice = new Address((int)$cart->id_address_invoice);

        $user_age = 'NO_ACCOUNT';
        $user_info_age = '';
        $registered = '';
        $txn_activity_today = '';
        $txn_activity_year = '';
        $txn_purchase_6 = '';
        $ship_name_indicator = 'DIFFERENT';
        if (!$customer->is_guest) {
            $registered = $customer->date_add;

            $diff = strtotime('now') - strtotime($registered);
            $days = (int)date('d', $diff);
            if ( $days === 0 ) {
                $user_age = 'JUST_CHANGED';
                $user_info_age = 'JUST_CHANGED';
            }  elseif ( $days < 31 ) {
                $user_age = 'LESS_30';
                $user_info_age = 'LESS_30';
            }  elseif ( $days < 61 ) {
                $user_age = 'BETWEEN_30_60';
                $user_info_age = 'BETWEEN_30_60';
            }  else {
                $user_age = 'MORE_60';
                $user_info_age = 'MORE_60';
            }

            if ( ($customer->firstname . ' ' . $customer->lastname) === 
                 ($address->firstname . ' ' . $address->lastname) ) {
                $ship_name_indicator = 'IDENTICAL';
            }
        }

        $acs = array(
            'CARDHOLDER'        => array(
                'NAME'          => $customer->firstname . ' ' . $customer->lastname,
                'EMAIL'         => $customer->email,
                'BILL_ADDRESS'  => array(
                    'CITY'      => $address_invoice->city,
                    'COUNTRY'   => $address_invoice->country,
                    'LINE1'     => $address_invoice->address1,
                    'LINE2'     => $address_invoice->address2,
                    'LINE3'     => '',
                    'POST_CODE' => $address_invoice->postcode,
                    'STATE'     => ''
                ),
            ),
            'PURCHASE'          => array(
                'SHIP_ADDRESS'  => array(
                    'CITY'      => $address->city,
                    'COUNTRY'   => $address->country,
                    'LINE1'     => $address->address1,
                    'LINE2'     => $address->address2,
                    'LINE3'     => '',
                    'POST_CODE' => $address->postcode,
                    'STATE'     => ''
                ),
                'MOBILE_PHONE'  => array(
                    'CC'        => '',
                    'SUBSCRIBER'=> $address->phone
                ),
                'WORK_PHONE'    => array(
                    'CC'        => '',
                    'SUBSCRIBER'=> ''
                ),
                'HOME_PHONE'    => array(
                    'CC'        => '',
                    'SUBSCRIBER'=> ''
                ),
            ),
            'MERCHANT_RISK_IND' => array(
                'SHIP_INDICATOR'=> 'CH_BILLING_ADDRESS',
                'DELIVERY_TIMEFRAME' => 'TWO_MORE_DAYS',
                'DELIVERY_EMAIL_ADDRESS' => $customer->email,
                'REORDER_ITEMS_IND' => 'FIRST_TIME_ORDERED',
                'PRE_ORDER_PURCHASE_IND' => 'AVAILABLE',
                'PRE_ORDER_DATE'=> '',
            ),
            'ACCOUNT_INFO'      => array(
                'CH_ACC_AGE_IND'=> $user_age,
                'CH_ACC_CHANGE_IND' => $user_info_age,
                'CH_ACC_CHANGE' => $registered,
                'CH_ACC_DATE'   => $registered,
                'TXN_ACTIVITY_DAY' => $txn_activity_today,
                'TXN_ACTIVITY_YEAR' => $txn_activity_year,
                'NB_PURCHASE_ACCOUNT' => $txn_purchase_6,
                'SUSPICIOUS_ACC_ACTIVITY' => 'NO_SUSPICIOUS',
                'SHIP_NAME_INDICATOR' => $ship_name_indicator,
                'PAYMENT_ACC_IND' => $user_age,
                'PAYMENT_ACC_AGE' => $registered
            )
        );

        $amount = number_format($cart->getOrderTotal(), 2);

        // Create transaction
        $cecabank_client->setFormHiddens(array(
            'Num_operacion' => $cart->id,
            'Descripcion' => printf(
                '%09d - %s %s',
                $cart->id,
                $customer->firstname,
                Tools::ucfirst(Tools::strtolower($customer->lastname))
            ),
            'Importe' => $amount,
            'URL_OK' => $url['return'],
            'URL_NOK' => $url['cancel'],
            'datos_acs_20' => urlencode( json_encode( $acs ) )
        ));
        
        echo '<form id="cecabank-form" action="'.$cecabank_client->getPath().'" method="post">'.$cecabank_client->getFormHiddens().'</form>'.'<script>document.getElementById("cecabank-form").submit();</script>';
    }

    protected function get_client_config() {
        $lang = '1';
        switch ($this->context->language->iso_code) {
            case 'en':
                $lang = '6';
                break;
            case 'fr':
                $lang = '7';
                break;
            case 'de':
                $lang = '8';
                break;
            case 'pt':
                $lang = '9';
                break;
            case 'it':
                $lang = '10';
                break;
            case 'ru':
                $lang = '14';
                break;
            case 'no':
                $lang = '15';
                break;
            case 'ca':
                $lang = '2';
                break;
            case 'eu':
                $lang = '3';
                break;
            case 'gl':
                $lang = '4';
                break;
            default:
                $lang = '1';
                break;
        }
        return array(
            'Environment' => Configuration::get('environment'),
            'MerchantID' => Configuration::get('merchant'),
            'AcquirerBIN' => Configuration::get('acquirer'),
            'TerminalID' => Configuration::get('terminal'),
            'ClaveCifrado' => Configuration::get('secret_key'),
            'TipoMoneda' => Configuration::get('currency'),
            'Exponente' => '2',
            'Cifrado' => 'SHA2',
            'Idioma' => $lang,
            'Pago_soportado' => 'SSL'
        );
    }
}

