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
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class Tendopay extends PaymentModule
{
    protected $config_form = false;
    private $html = '';
    private $postErrors = array();

    public function __construct()
    {
        $this->name = 'tendopay';
        $this->prefix = Tools::strtoupper($this->name);
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'TendoPay';
        $this->need_instance = 0;

        $this->controllers = array('confirmation', 'redirect', 'validation');

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Buy now, pay later with TendoPay');
        $this->description = $this->l('Accept local payment methods for LATAM using your Tendopay Merchant account.');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        $this->moduleConfigs = array(
            'DIRECTO_OS_PENDING_PAYMENT' => (int)Configuration::get('TENDOPAY_OS_PENDING')
        );
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }

        // $iso_code = Country::getIsoById(Configuration::get('PS_COUNTRY_DEFAULT'));

        // Registration order status
        if (!$this->installOrderState()) {
            return false;
        }

        $this->moduleConfigs['DIRECTO_OS_PENDING_PAYMENT'] = (int)Configuration::get('TENDOPAY_OS_PENDING');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('actionCarrierProcess') &&
            $this->registerHook('actionCarrierUpdate') &&
            $this->registerHook('actionPaymentConfirmation') &&
            $this->registerHook('displayBackOfficeHome') &&
            $this->registerHook('actionAdminControllerSetMedia') &&
            $this->registerHook('displayPaymentReturn') &&
            $this->registerHook('moduleRoutes') &&
            $this->registerHook('displayShoppingCart');
    }

    public function uninstall()
    {
        Configuration::deleteByName($this->prefix.'_LIVE_MODE');
        Configuration::deleteByName($this->prefix.'_LIVE_CLIENT_SECRET');
        Configuration::deleteByName($this->prefix.'_LIVE_CLIENT_ID');
        Configuration::deleteByName($this->prefix.'_LIVE_AUTH_URL');
        Configuration::deleteByName($this->prefix.'_LIVE_ORDER_URL');
        Configuration::deleteByName($this->prefix.'_SANDBOX_CLIENT_SECRET');
        Configuration::deleteByName($this->prefix.'_SANDBOX_CLIENT_ID');
        Configuration::deleteByName($this->prefix.'_SANDBOX_AUTH_URL');
        Configuration::deleteByName($this->prefix.'_SANDBOX_ORDER_URL');

        return parent::uninstall();
    }

    /**
      * Create order state
      * @return boolean
      */
    public function installOrderState()
    {
        if (!Configuration::get('TENDOPAY_OS_PENDING')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('TENDOPAY_OS_PENDING')))) {
            $order_state = new OrderState();
            $order_state->name = array();
            foreach (Language::getLanguages() as $language) {
                if (Tools::strtolower($language['iso_code']) == 'fr') {
                    $order_state->name[$language['id_lang']] = 'Pending Tendopay payment';
                } else {
                    $order_state->name[$language['id_lang']] = 'Pending Tendopay payment';
                }
            }
            $order_state->send_email = false;
            $order_state->color = '#de4375';
            $order_state->hidden = false;
            $order_state->delivery = false;
            $order_state->logable = false;
            $order_state->invoice = false;
            $order_state->module_name = $this->name;

            if ($order_state->add()) {
            }

            if (Shop::isFeatureActive()) {
                $shops = Shop::getShops();
                $odStateid = (int) $order_state->id;

                foreach ($shops as $shop) {
                    Configuration::updateValue('TENDOPAY_OS_PENDING', $odStateid, false, null, (int)$shop['id_shop']);
                }
            } else {
                Configuration::updateValue('TENDOPAY_OS_PENDING', $odStateid);
            }
        }

        return true;
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $this->html = '';

        if (Tools::isSubmit('submitDirectopagoModule')) {
            $this->postValidation();
            if (!count($this->postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->html .= $this->displayError($err);
                }
            }
        }
        $notification_url = $this->context->link->getModuleLink('tendopay', 'paymentsuccess', array());
        $this->context->smarty->assign('notification_url', $notification_url);
        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
        $this->html .= $this->displayInstallationtips();
        $this->html .= $output.$this->renderForm();

        return $this->html;
    }

    protected function displayInstallationtips()
    {
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitDirectopagoModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        $test1= $helper->generateForm(array($this->getConfigForm()));

        //$test1 .= $helper->generateForm(array($this->getConfigForm1()));

        return $test1;
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        $listmodes = array();
        $listmodes[] = array('id_mode' => 0, 'name' => 'Sandbox mode',);
        $listmodes[] = array('id_mode' => 1, 'name' => 'Live mode');

        $tendolive = 'Tendopay Live Credentials';

        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Tendopay Settings'),
                    'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'select',
                        'label' => $this->l('Tendopay Mode'),
                        'desc' => $this->l('Choose payment mode'),
                        'name' => $this->prefix.'_LIVE_MODE',
                        'required' => true,
                        'options' => array(
                            'query' => $listmodes,
                            'id' => 'id_mode',
                            'name' => 'name',
                        ),
                    ),
                    array(
                        'label' => '<h3 class="modal-title text-info live-mode">'.$this->l($tendolive).'</h3>',
                        'type' => 'free',
                        'class' => 'live-mode',
                        'name' => 'FREE',
                        'desc' => '<br><hr>',
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
                        'label' => $this->l('Client Secret'),
                        'required' => true,
                        'desc' => $this->l('Enter an Client Secret'),
                        'name' => $this->prefix.'_LIVE_CLIENT_SECRET',
                        'label' => $this->l('Client Secret'),
                        'class' =>'txbx live-mode',
                        'hidden'=>'',
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
                        'label' => $this->l('Client Id'),
                        'required' => true,
                        'desc' => $this->l('Enter an Client id'),
                        'name' => $this->prefix.'_LIVE_CLIENT_ID',
                        'label' => $this->l('Client Id'),
                        'class' =>'txbx live-mode',
                        'hidden'=>'',
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
                        'label' => $this->l('Auth Url'),
                        'required' => true,
                        'desc' => $this->l('Enter an Auth url'),
                        'name' => $this->prefix.'_LIVE_AUTH_URL',
                        'label' => $this->l('Auth Url'),
                        'class' =>'txbx live-mode',
                        'hidden'=>'',
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
                        'label' => $this->l('Order Url'),
                        'required' => true,
                        'desc' => $this->l('Enter an Order url'),
                        'name' => $this->prefix.'_LIVE_ORDER_URL',
                        'label' => $this->l('Order Url'),
                        'class' =>'txbx live-mode',
                        'hidden'=>'',
                    ),
                    array(
                        'label' => '<h3 class="modal-title text-info">'.$this->l('Tendopay Sandbox Credential').'</h3>',
                        'type' => 'free',
                        'class' => 'sandbox-mode',
                        'name' => 'FREE',
                        'desc' => '<br><hr>',
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
                        'label' => $this->l('Client Secret'),
                        'required' => true,
                        'desc' => $this->l('Enter an Client Secret'),
                        'name' => $this->prefix.'_SANDBOX_CLIENT_SECRET',
                        'label' => $this->l('Client Secret'),
                        'class' =>'txbx sandbox-mode',
                        'hidden'=>'',
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
                        'label' => $this->l('Client Id'),
                        'required' => true,
                        'desc' => $this->l('Enter an Client id'),
                        'name' => $this->prefix.'_SANDBOX_CLIENT_ID',
                        'label' => $this->l('Client Id'),
                        'class' =>'txbx sandbox-mode',
                        'hidden'=>'',
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
                        'label' => $this->l('Auth Url'),
                        'required' => true,
                        'desc' => $this->l('Enter an Auth url'),
                        'name' => $this->prefix.'_SANDBOX_AUTH_URL',
                        'label' => $this->l('Auth Url'),
                        'class' =>'txbx sandbox-mode',
                        'hidden'=>'',
                    ),
                    array(
                        'col' => 6,
                        'type' => 'text',
                        'label' => $this->l('Order Url'),
                        'required' => true,
                        'desc' => $this->l('Enter an Order url'),
                        'name' => $this->prefix.'_SANDBOX_ORDER_URL',
                        'label' => $this->l('Order Url'),
                        'class' =>'txbx sandbox-mode',
                        'hidden'=>'',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    private function postValidation()
    {
        $sandboxAuthurl = 'The "Sandbox Auth Url" field is required.';
        $sandboxOrderurl = 'The "Sandbox Order Url" field is required.';
        $sandboxClientsecret = 'The "Sandbox Client Secret" field is required.';
        $sandboxClientid= 'The "Sandbox Client Id" field is required.';
        $liveClientsecret = 'The "Live Client Secret" field is required.';
        $liveClientid = 'The "Live Client Id" field is required.';
        $liveAuthurl = 'The "Live Auth Url" field is required.';
        $liveOrderurl = 'The "Live Order Url" field is required.';

        if (Tools::isSubmit('submitDirectopagoModule')) {
            if (Tools::getValue('TENDOPAY_LIVE_MODE')==1) {
                if (!Tools::getValue($this->prefix.'_LIVE_CLIENT_SECRET')) {
                    $this->postErrors[] = $this->trans($liveClientsecret, array(), 'Modules.Tendopay.Admin');
                }
                if (!Tools::getValue($this->prefix.'_LIVE_CLIENT_ID')) {
                    $this->postErrors[] = $this->trans($liveClientid, array(), 'Modules.Tendopay.Admin');
                }
                if (!Tools::getValue($this->prefix.'_LIVE_AUTH_URL')) {
                    $this->postErrors[] = $this->trans($liveAuthurl, array(), 'Modules.Tendopay.Admin');
                }
                if (!Tools::getValue($this->prefix.'_LIVE_ORDER_URL')) {
                    $this->postErrors[] = $this->trans($liveOrderurl, array(), 'Modules.Tendopay.Admin');
                }
            } else {
                if (!Tools::getValue($this->prefix.'_SANDBOX_CLIENT_SECRET')) {
                    $this->postErrors[] = $this->trans($sandboxClientsecret, array(), 'Modules.Tendopay.Admin');
                }
                if (!Tools::getValue($this->prefix.'_SANDBOX_CLIENT_ID')) {
                    $this->postErrors[] = $this->trans($sandboxClientid, array(), 'Modules.Tendopay.Admin');
                }
                if (!Tools::getValue($this->prefix.'_SANDBOX_AUTH_URL')) {
                    $this->postErrors[] = $this->trans($sandboxAuthurl, array(), 'Modules.Tendopay.Admin');
                }
                if (!Tools::getValue($this->prefix.'_SANDBOX_ORDER_URL')) {
                    $this->postErrors[] = $this->trans($sandboxOrderurl, array(), 'Modules.Tendopay.Admin');
                }
            }
        }
    }

    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        $setUpdate = 'Settings updated';

        $this->html .= $this->displayConfirmation($this->trans($setUpdate, array(), 'Admin.Notifications.Success'));
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            $this->prefix.'_LIVE_MODE' => Configuration::get($this->prefix.'_LIVE_MODE'),
            $this->prefix.'_LIVE_CLIENT_SECRET' => Configuration::get($this->prefix.'_LIVE_CLIENT_SECRET'),
            $this->prefix.'_LIVE_CLIENT_ID' => Configuration::get($this->prefix.'_LIVE_CLIENT_ID'),
            $this->prefix.'_LIVE_AUTH_URL' => Configuration::get($this->prefix.'_LIVE_AUTH_URL'),
            $this->prefix.'_LIVE_ORDER_URL' => Configuration::get($this->prefix.'_LIVE_ORDER_URL'),
            $this->prefix.'_SANDBOX_CLIENT_SECRET' => Configuration::get($this->prefix.'_SANDBOX_CLIENT_SECRET'),
            $this->prefix.'_SANDBOX_CLIENT_ID' => Configuration::get($this->prefix.'_SANDBOX_CLIENT_ID'),
            $this->prefix.'_SANDBOX_AUTH_URL' => Configuration::get($this->prefix.'_SANDBOX_AUTH_URL'),
            $this->prefix.'_SANDBOX_ORDER_URL' => Configuration::get($this->prefix.'_SANDBOX_ORDER_URL'),
        );
    }

    public static function isPs17()
    {
        return (bool)version_compare(_PS_VERSION_, '1.7', '>=');
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */

    public function hookActionAdminControllerSetMedia()
    {
        // has to be loaded in header to prevent flash of content
        //echo Tools::getValue('module_name'); exit;
        // if (Tools::getValue('module_name') == $this->name) {
        $this->context->controller->addJs($this->getPathUri() . 'views/js/back.js?v=' . $this->version);
        // }
    }

    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            //$this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false) {
            return;
        }
        

        $order = $params['objOrder'];

        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')) {
            $this->smarty->assign('status', 'ok');
        }
        

        $this->smarty->assign(array(
            'id_order' => $order->id,
            'reference' => $order->reference,
            'params' => $params,
            'total' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
        ));

        return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
    }

    /**
     * Return payment options available for PS 1.7+
     *
     * @param array Hook parameters
     *
     * @return array|null
     */
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign(
            $this->getTemplateVars()
        );

        $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($this->trans('Pay with Tendopay', array(), 'Modules.Tendopay.Admin'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true));

        return [
            $option
            ];
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }

    public function hookActionCarrierProcess()
    {
        /* Place your code here. */
    }

    public function hookActionCarrierUpdate()
    {
        /* Place your code here. */
    }

    public function hookActionPaymentConfirmation()
    {
        /* Place your code here. */
    }

    public function hookDisplayBackOfficeHome()
    {
        /* Place your code here. */
    }


    public function hookDisplayPaymentReturn()
    {
        /* Place your code here. */
    }

    public function hookDisplayShoppingCart()
    {
        /* Place your code here. */
    }

    public function getTemplateVars()
    {
        $cart = $this->context->cart;
        $total = $this->trans(
            '%amount% (tax incl.)',
            array(
                '%amount%' => Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH)),
            ),
            'Modules.Tendopay.Admin'
        );

        return [
            'checkTotal' => $total,
        ];
    }

    public function hookModuleRoutes()
    {
        return array(
            'module-'.$this->name.'-directoback' => array(
                'controller' => 'directoback',
                'rule' => 'directoback',
                'keywords' => array(),
                'params' => array(
                    'fc' => 'module',
                    'module' => $this->name,
                    'controller' => 'directoback',
                ),
            ),
            'module-'.$this->name.'-directosuccess' => array(
                'controller' => 'directosuccess',
                'rule' => 'directosuccess',
                'keywords' => array(),
                'params' => array(
                    'fc' => 'module',
                    'module' => $this->name,
                    'controller' => 'directosuccess',
                ),
            ),
            'module-'.$this->name.'-paymentsuccess' => array(
                'controller' => 'paymentsuccess',
                'rule' => $this->name.'/paymentsuccess',
                'keywords' => array(),
                'params' => array(
                    'fc' => 'module',
                    'module' => $this->name,
                ),
            ),
         );
    }
}
