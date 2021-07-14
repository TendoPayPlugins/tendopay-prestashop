<?php
/*** 2007-2020 PrestaShop
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
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

class TendopayPaymentSuccessModuleFrontController extends ModuleFrontController
{

    public function init()
    {
        parent::init();
    }
    public function initContent()
    {
        parent::initContent();
        //$tp_merchant_order_id=Tools::getValue('tp_merchant_order_id');
        $tendopay_error = (!empty(Tools::getValue('tp_message'))) ? Tools::getValue('tp_message') : '';
        if (Tools::getValue('tp_transaction_status') == 'PAID') {
            // Change Order status to paid
            $order_updatestatus = new Order((int)Tools::getValue('tp_merchant_order_id'));
            $order_updatestatus->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));
            // Get module info
            $module = Module::getInstanceByName('tendopay');
            // Get order info
            $order = new Order((int)Tools::getValue('tp_merchant_order_id'));
            // Update Transaction Id
            Db::getInstance()->update('order_payment', array(
                'transaction_id' => Tools::getValue('tp_transaction_id'),
            ), 'order_reference = ');

            $secure_key = Context::getContext()->customer->secure_key;
            // Redirect to order confirmation page
            $orderConfirmUrl = 'index.php?controller=order-confirmation&id_cart='.(int)$order->id_cart.'&id_module=';
            $orderConfirmUrl .= (int)$module->id.'&id_order='.(int)Tools::getValue('tp_merchant_order_id');
            Tools::redirect($orderConfirmUrl.'&key='.$secure_key);
        } else {
            // Update Order Status to (Payment Error)

            $order_payment_error = new Order((int)Tools::getValue('tp_merchant_order_id'));

            if (!empty($order_payment_error)) {
                $cart_id = $order_payment_error->id_cart;
                $cart= new Cart((int)$cart_id);
                $products= $cart->getProducts(true);
                 //echo 'cart<pre>'; print_r($products); exit;

                if (!empty($products)) {
                    $this->context->cart->add();
                    foreach ($products as $key => $line) {
                        $this->context->cart->updateQty((int)$line['cart_quantity'], $line['id_product'], $line['id_product_attribute'], false);
                    }
                        $this->context->cookie->id_cart = $this->context->cart->id;
                        $this->context->cookie->write();
                }
            }
            

            $order_payment_error->setCurrentState((int)Configuration::get('PS_OS_ERROR'));
            // Update Order status to (Canceled)
            $order_canceled = new Order((int)Tools::getValue('tp_merchant_order_id'));
            $order_canceled->setCurrentState((int)Configuration::get('PS_OS_CANCELED'));
                $this->context->smarty->assign('tendopay_error_msg', $tendopay_error);
            if (Tendopay::isPs17()) {
                $this->setTemplate('module:tendopay/views/templates/front/paymenterror.tpl');
            } else {
                $this->setTemplate('paymenterror-1-6.tpl');
            }
        }
    }
}
