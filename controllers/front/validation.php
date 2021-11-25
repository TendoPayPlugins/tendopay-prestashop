<?php
/**
* 2007-2020 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class TendopayValidationModuleFrontController extends ModuleFrontController
{
    /**
     * This class should be use by your Instant Payment
     * Notification system to validate the order remotely
     */
    public function postProcess()
    {
        /*
         * If the module is not active anymore, no need to process anything.
         */

        $cart = $this->context->cart;

        $cartidCustomer = $cart->id_customer;
        $cartidAdressDelivery = $cart->id_address_delivery;
        $cartidAdressInvoice = $cart->id_address_invoice;

        if ($cartidCustomer == 0 || $cartidAdressDelivery == 0 || $cartidAdressInvoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        // Check payment option is available in case if address change before the end of the checkout
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'tendopay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->trans('This payment method is not available.', array(), 'Modules.Tendopay.Shop'));
        }

        /**
         * Since it is an example, we choose sample data,
         * You'll have to get the correct values :)
         */
        $cart_id = $cart->id;
        $customer_id = $cart->id_customer;
        $amount = (float)$cart->getOrderTotal(true, Cart::BOTH);

        /*
         * Restore the context from the $cart_id & the $customer_id to process the validation properly.
         */
        Context::getContext()->cart = new Cart((int) $cart_id);
        Context::getContext()->customer = new Customer((int) $customer_id);

        if (!Validate::isLoadedObject(Context::getContext()->customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        Context::getContext()->currency = new Currency((int) Context::getContext()->cart->id_currency);
        Context::getContext()->language = new Language((int) Context::getContext()->customer->id_lang);
        $secure_key = Context::getContext()->customer->secure_key;

        if ($this->isValidOrder() === true) {
            $payment_status = Configuration::get('TENDOPAY_OS_PENDING');
            $message = null;
        } else {
            $payment_status = Configuration::get('PS_OS_ERROR');
            /**
             * Add a message to explain why the order has not been validated
             */
            $message = $this->module->l('An error occurred while processing payment');
        }
        $secure_key = Context::getContext()->customer->secure_key;
        $module_name = $this->module->displayName;
        $currency_id = (int) Context::getContext()->currency->id;

        $this->module->validateOrder($cart_id, $payment_status, $amount, $module_name, $message, array(), $currency_id, false, $secure_key);
        //diretopago success
        $directopago_url = $this->getCheckoutUrl($this->module->currentOrder, $cart);

        if ($directopago_url['url']) {
            Tools::redirect($directopago_url['url']);
            exit;
        } else {
            $redUrl = 'index.php?controller=order-confirmation&Status=failed&error_code=';
            $redUrl .= $directopago_url['errorCode'].'&error_message='.$directopago_url['errorMessage'].'&id_cart=';
            $othersUrl = (int)$cart_id.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder;
            Tools::redirect($redUrl.$othersUrl.'&key='.$secure_key);
        }
    }

    protected function isValidOrder()
    {
        /*
         * Add your checks right there
         */
        return true;
    }


    protected function getCheckoutUrl($order_id, $cart)
    {
        $secure_key = Context::getContext()->customer->secure_key;
        $id_delivery_address = $cart->id_address_delivery;
        $customer =new Customer((int) $cart->id_customer);
        $address = new Address($id_delivery_address);
        $amount = (float)$cart->getOrderTotal(true, Cart::BOTH);

        $cartid = $cart->id;
        $country_id =$address->id_country;
        $country_iso = Country::getIsoById($country_id);
        $currency_id = (int) Context::getContext()->currency->id;
        $currency = new CurrencyCore($currency_id);
        $order = new Order($order_id);
        $ref = $order->reference;
        /*$backurl = Context::getContext()->shop->getBaseURL(true).'directoback?cancel_order=true&id_cart='.(int)$order->id_cart.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$secure_key;*/
        /*$successurl = Context::getContext()->shop->getBaseURL(true).'directosuccess?status=success&id_cart='.(int)$order->id_cart.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$secure_key;*/
        $textContent = Context::getContext()->shop->getBaseURL(true).'directosuccess?status=success&id_cart=';
        $orderKey = $this->module->currentOrder.'&key='.$secure_key;

        $successurl = $textContent.(int)$order->id_cart.'&id_module='.(int)$this->module->id.'&id_order='.$orderKey;

        $body = array(
            "invoiceId" => $ref,
            "clientId" => $cart->id_customer,
            "clientFirstName" => $address->firstname,
            "clientLastName" => $address->lastname,
            "clientEmail" => $customer->email,
            "clientAddress" => $address->address1,
            "clientCity" => $address->city,
            "clientZipcode" => $address->postcode,
            "clientMobilePhone" => $address->phone,
            "amount" => $amount,
            "currency" => $currency->iso_code,
            "country" => $country_iso,
            "backUrl" => $successurl,
            "successUrl" =>  $successurl,
            "id_order"=> $this->module->currentOrder
        );

        $response = $this->requestCurl($body);
        $directopago_url = $this->getCheckoutUrl($this->module->currentOrder, $cart);

        if ($response['errorCode']==103) {
            $responseUrl = 'index.php?controller=order-confirmation&Status=failed&error_code=';
            $responseUrl .= $directopago_url['errorCode'].'&error_message='.$directopago_url['errorMessage'];
            $responseUrl .= '&id_cart='.(int)$cartid.'&id_module='.(int)$this->module->id;
            Tools::redirect($responseUrl.'&id_order='.$this->module->currentOrder.'&key='.$secure_key);
            /*Tools::redirect('index.php?controller=order-confirmation&Status=failed&error_code='.$directopago_url['errorCode'].'&error_message='.$directopago_url['errorMessage'].'&id_cart='.(int)$cartid.'&id_module='.(int)$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$secure_key);*/
        }

        return $response;
    }

    protected function requestCurl(array $body)
    {
        $redirect_url = $this->context->link->getModuleLink('tendopay', 'paymentsuccess', array());
        $mode = Configuration::get('TENDOPAY_LIVE_MODE');
        if (!empty($mode)) {
            $client_secret = Configuration::get('TENDOPAY_LIVE_CLIENT_SECRET');
            $client_id = Configuration::get('TENDOPAY_LIVE_CLIENT_ID');
            $token_url = 'https://app.tendopay.ph/oauth/token';
            $order_url = 'https://app.tendopay.ph/payments/api/v2/order';
        //$payment_authorize_url = Configuration::get('TENDOPAY_LIVE_PAYMENT_AUTHORIZE_URL');
        } else {
            $client_secret = Configuration::get('TENDOPAY_SANDBOX_CLIENT_SECRET');
            $client_id = Configuration::get('TENDOPAY_SANDBOX_CLIENT_ID');
            $token_url = 'https://sandbox.tendopay.ph/oauth/token';
            $order_url = 'https://sandbox.tendopay.ph/payments/api/v2/order';
            //$payment_authorize_url = Configuration::get('TENDOPAY_SANDBOX_PAYMENT_AUTHORIZE_URL');
        }

        $body = array(
            'client_secret' => $client_secret,
            'client_id' => $client_id,
            'grant_type'=>'client_credentials'
        );
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $token_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => array(
                "content-type: application/json; charset=utf-8",
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            $reponsejason= json_decode($response, true);
            $accesstoken=$reponsejason['access_token'];
            if (!empty($accesstoken)) {
                $cart = $this->context->cart;
                $customer_id = $cart->id_customer;
                $amount = (int)$cart->getOrderTotal(true, Cart::BOTH);
                $order_description = $cart->getLastProduct();
                //$currency_id = (int) Context::getContext()->currency->id;
                //$currency = new CurrencyCore($currency_id);
                $body_hash = array(
                    'tp_amount' => $amount,
                    'tp_currency' => 'PHP',
                    'tp_merchant_order_id' => ''.$this->module->currentOrder.'',
                    'tp_redirect_url' => $redirect_url,
                    'tp_merchant_user_id' => ''.$customer_id.'',
                    'tp_description' => $order_description['name'],
                );

                ksort($body_hash);
                $message = array_reduce(array_keys($body_hash), static function ($p, $k) use ($body_hash) {
                    return strpos($k, 'tp_') === 0 ? $p.$k.trim($body_hash[$k]) : $p;
                }, '');
                $hash = hash_hmac('sha256', $message, $client_secret);
                $body12 = array(
                    'tp_amount' => $amount,
                    'tp_currency' => 'PHP',
                    'tp_merchant_order_id' => ''.$this->module->currentOrder.'',
                    'tp_redirect_url' => $redirect_url,
                    'tp_merchant_user_id' => ''.$customer_id.'',
                    'tp_description' => $order_description['name'],
                    'access_token' => $accesstoken,
                    'x_signature' =>$hash
                );

                $curl = curl_init();
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $order_url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => "",
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => json_encode($body12, JSON_UNESCAPED_UNICODE),
                    CURLOPT_HTTPHEADER => array(
                        "Accept: application/json;",
                        "content-type: application/json;",
                        "Authorization: Bearer ".$accesstoken,
                    ),
                ));

                $responses = curl_exec($curl);

                $reponsesjason= json_decode($responses, true);
                $redFinalurl = $reponsesjason['authorize_url'];

                //$order_accesstoken=$reponsesjason['tp_order_token'];

                if (!empty($responses)) {
                    Tools::redirect($redFinalurl);
                    exit;
                }
            } else {
            }
        }
    }
}
