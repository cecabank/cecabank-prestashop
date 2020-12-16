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
            'notify' => $baseUrl . '/validation.php'
        );

        if (_PS_VERSION_ <= '1.5') {
            $url['return'] = $this->context->shop->getBaseURL() . 'index.php?controller=order-confirmation&id_cart='.
                $cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.
                $customer->secure_key;
            $url['cancel'] = $this->context->shop->getBaseURL() . 'index.php?controller=order&step=3';
        } else {
            $url['return'] = $this->context->link->getPageLink('order-confirmation', null, null, array(
                'id_cart' => $cart->id,
                'id_module' => $this->module->id,
                'key' => $customer->secure_key,
                'id_order' => $this->module->currentOrder
            ));
            $url['cancel'] = $this->context->link->getPageLink('order', null, null, array(
                'step' => '3'
            ));
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

        // ACS
        $acs = array();

        // Cardholder
        $cardholder = array();
        $add_cardholder = false;

        // Cardholder bill address
        $bill_address = array();
        $add_bill_address = false;
        if ($address_invoice->country) {
            $bill_address['CITY'] = $address_invoice->country;
            $add_bill_address = true;
        }                
        if ($address_invoice->country) {
            $bill_address['COUNTRY'] = $address_invoice->country;
            $add_bill_address = true;
        }
        if ($address_invoice->address1) {
            $bill_address['LINE1'] = $address_invoice->address1;
            $add_bill_address = true;
        }                
        if ($address_invoice->address2) {
            $bill_address['LINE2'] = $address_invoice->address2;
            $add_bill_address = true;
        }
        if ($address_invoice->postcode) {
            $bill_address['POST_CODE'] = $address_invoice->postcode;
            $add_bill_address = true;
        } 
        if ($add_bill_address) {
            $cardholder['BILL_ADDRESS'] = $bill_address;
            $add_cardholder = true;
        }

        // Cardholder name
        if ($customer->firstname || $customer->lastname) {
            $cardholder['NAME'] = $customer->firstname . ' ' . $customer->lastname;
            $add_cardholder = true;
        }

        // Cardholder email
        if ($customer->email) {
            $cardholder['EMAIL'] = $customer->email;
            $add_cardholder = true;
        }

        if ($add_cardholder) {
            $acs['CARDHOLDER'] = $cardholder;
        }

        // Purchase
        $purchase = array();
        $add_purchase = true;

        // Purchase ship address
        $ship_address = array();
        $add_ship_address = false;
        if ($address->city) {
            $ship_address['CITY'] = $address->city;
            $add_ship_address = true;
        }                
        if ($address->country) {
            $ship_address['COUNTRY'] = $address->country;
            $add_ship_address = true;
        }
        if ($address->address1) {
            $ship_address['LINE1'] = $address->address1;
            $add_ship_address = true;
        }                
        if ($address->address2) {
            $ship_address['LINE2'] = $address->address2;
            $add_ship_address = true;
        }
        if ($address->postcode) {
            $ship_address['POST_CODE'] = $address->postcode;
            $add_ship_address = true;
        }
        if ($add_ship_address) {
            $purchase['SHIP_ADDRESS'] = $ship_address;
            $add_purchase = true;
        }

        // Purchase mobile phone
        if ($address->phone) {
            $purchase['MOBILE_PHONE'] = array(
                'SUBSCRIBER' => $address->phone
            );
            $add_purchase = true;
        }

        if ($add_purchase) {
            $acs['PURCHASE'] = $purchase;
        }

        // Merchant risk
        $merchant_risk = array(
            'SHIP_INDICATOR'=> 'CH_BILLING_ADDRESS',
            'DELIVERY_TIMEFRAME' => 'TWO_MORE_DAYS',
            'REORDER_ITEMS_IND' => 'FIRST_TIME_ORDERED',
            'PRE_ORDER_PURCHASE_IND' => 'AVAILABLE',
        );
        if ($customer->email) {
            $merchant_risk['DELIVERY_EMAIL_ADDRESS'] = $customer->email;
        }
        $acs['MERCHANT_RISK_IND'] = $merchant_risk;

        // Account info
        $account_info = array(
            'SUSPICIOUS_ACC_ACTIVITY' => 'NO_SUSPICIOUS'
        );
        if ($user_age) {
            $account_info['CH_ACC_AGE_IND'] = $user_age;
            $account_info['PAYMENT_ACC_IND'] = $user_age;
        }
        if ($user_info_age) {
            $account_info['CH_ACC_CHANGE_IND'] = $user_info_age;
        }
        if ($registered) {
            $account_info['CH_ACC_CHANGE'] = $registered;
            $account_info['CH_ACC_DATE'] = $registered;
            $account_info['PAYMENT_ACC_AGE'] = $registered;
        }
        if ($txn_activity_today) {
            $account_info['TXN_ACTIVITY_DAY'] = $txn_activity_today;
        }
        if ($txn_activity_year) {
            $account_info['TXN_ACTIVITY_YEAR'] = $txn_activity_year;
        }
        if ($txn_purchase_6) {
            $account_info['NB_PURCHASE_ACCOUNT'] = $txn_purchase_6;
        }
        if ($ship_name_indicator) {
            $account_info['SHIP_NAME_INDICATOR'] = $ship_name_indicator;
        }
        $acs['ACCOUNT_INFO'] = $account_info;

        $amount = number_format($cart->getOrderTotal(), 2);

        // Create transaction
        $cecabank_client->setFormHiddens(array(
            'Num_operacion' => $cart->id,
            'Descripcion' => $cart->id.' - '.$customer->firstname.' '.Tools::ucfirst(Tools::strtolower($customer->lastname)),
            'Importe' => $amount,
            'URL_OK' => $url['return'],
            'URL_NOK' => $url['cancel'],
            'datos_acs_20' => urlencode( json_encode( $acs ) )
        ));
        
        echo '<form id="cecabank-form" action="'.$cecabank_client->getPath().'" method="post">'.$cecabank_client->getFormHiddens().'</form>'.'<script>document.getElementById("cecabank-form").submit();</script>';
        die();
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

