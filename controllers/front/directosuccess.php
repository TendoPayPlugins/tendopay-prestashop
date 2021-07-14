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
*  @copyright 2007-2021 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Adapter\Order\OrderPresenter;

class TendopayDirectosuccessModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {
        parent::initContent();
      
       
        $id_order=Tools::getValue('id_order');
        //$secure_key = Context::getContext()->customer->secure_key;
       // Load order object
        $order = new Order($id_order);
        $order_presenter = new OrderPresenter();
        $presentedOrder = $order_presenter->present($order);
        $this->secure_key = Tools::getValue('key');
             
        if ($id_order) {
            if (!Validate::isLoadedObject($order) || empty($this->secure_key)) {
                //$historypage = $this->context->link->getPageLink('history');
                if ($this->context->customer->isLogged()) {
                    Tools::redirect('index.php?controller=history');
                } else {
                    Tools::redirect('index.php');
                }
            } else {
                if ($order) {
                    $params = array('order' => $order);
                    $hook   = Hook::exec('OrderConfirmation', $params);

                    $customer = new Customer((int) $order->id_customer);
                    $cart = new Cart((int) $order->id_cart);

                            $this->context->smarty->assign(array(
                        'order' => $presentedOrder,
                         
                        'shipping_price' => 0,
                        'HOOK_ORDER_CONFIRMATION' => $hook,
                        'cart' => $cart,
                        'customer' => $customer,
                        'id_address_delivery' => $order->id_address_delivery,
                        'secure_key' => $order->secure_key,
                            ));
                         return $this->setTemplate('module:tendopay/views/templates/front/directosuccess.tpl');
                }
            }
        } else {
                //$historypage = $this->context->link->getPageLink('history');

            if ($this->context->customer->isLogged()) {
                    Tools::redirect('index.php?controller=history');
            } else {
                    Tools::redirect('index.php');
            }
        }
    }
}
