<?php
/**
* 2007-2019 PrestaShop
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

if (!defined('_PS_VERSION_')) {
    exit;
}

class Datatrics extends Module
{
    /**
     * Hooks for this module
     *
     * @var array $hooks
     */
    public $hooks = array(
        'displayHeader',
        'displayOrderConfirmation',
        'actionObjectOrderPaymentAddAfter',
        'top',
    );

    /**
     * Projectid
     * 
     * @var int
     */
    public $projectid;

    /**
     * live
     * 
     * @var bool
     */
    public $live;
    
    /**
     * Datatrics constructor.
     *
     * @throws ErrorException
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function __construct()
    {
        $this->name = 'datatrics';
        $this->tab = 'smart_shopping';
        $this->version = '1.0.0';
        $this->author = 'Datatrics B.V.';
        $this->need_instance = 0;

        $config = Configuration::getMultiple(array('DATATRICS_LIVE_MODE', 'DATATRICS_PROJECTID'));
        if (isset($config['DATATRICS_LIVE_MODE'])) {
            $this->live = $config['DATATRICS_LIVE_MODE'];
        }
        if (isset($config['DATATRICS_PROJECTID'])) {
            $this->projectid = $config['DATATRICS_PROJECTID'];
        }

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Datatrics');
        $this->description = $this->l('Datatrics intergration for Prestashop');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the intergration with Datatrics');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        if (!isset($this->projectid)|| empty($this->projectid)) {
            $this->warning = $this->l('The "Project ID" fields must be configured before using this module.');
        }
    }

    /**
     * Installs the Datatrics Intergration
     *
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws Adapter_Exception
     */
    public function install(): bool
    {
        if (!parent::install() && $this->installTab()) {
            $this->_errors[] = $this->l('There was an error during the installation.');
            return false;
        }

        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        foreach ($this->hooks as $hook) {
            $this->registerHook($hook);
        }

        Configuration::updateValue('DATATRICS_LIVE_MODE', false);
        
        return true;
    }

    /**
     * @return bool
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function uninstall(): bool
    {
        if (!parent::install() && $this->uninstallTab()) {
            $this->_errors[] = $this->l('There was an error during the uninstall.');
            return false;
        }

        foreach ($this->hooks as $hook) {
            $this->unregisterHook($hook);
        }

        Configuration::deleteByName('DATATRICS_LIVE_MODE');
        Configuration::deleteByName('DATATRICS_PROJECTID');
        Configuration::deleteByName('DATATRICS_APIKEY');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * @return bool
     * 
     * @param bool $force_all
     */
    public function enable($force_all = false): bool
    {
        return parent::enable($force_all);
    }

    /**
     * @return bool
     * 
     * @param bool $force_all
     */
    public function disable($force_all = false)
    {
        return parent::disable($force_all);
    }

    /**
     * @return string
     *
     * @throws Exception
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     * @throws Adapter_Exception
     */
    public function getContent(): string
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitDatatricsModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * @return string
     *
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     * @throws SmartyException
     */
    protected function renderForm(): string
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitDatatricsModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * @return array
     * 
     * @throws PrestaShopException
     */
    protected function getConfigForm(): array
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'DATATRICS_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('Enter your projectid'),
                        'name' => 'DATATRICS_PROJECTID',
                        'label' => $this->l('Project ID'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'DATATRICS_APIKEY',
                        'desc' => $this->l('Enter your apikey'),
                        'label' => $this->l('API Key'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * @return array
     * 
     * @throws PrestaShopException
     */
    protected function getConfigFormValues(): array
    {
        return array(
            'DATATRICS_LIVE_MODE' => Configuration::get('DATATRICS_LIVE_MODE', true),
            'DATATRICS_PROJECTID' => Configuration::get('DATATRICS_PROJECTID', null),
            'DATATRICS_APIKEY' => Configuration::get('DATATRICS_APIKEY', null),
        );
    }

    /**
     * Save form data.
     * 
     * @return void
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }
    /**
     * @return string
     * 
     * Add the JavaScript for the Datatrics Tracker
     */
    public function hookDisplayHeader(): string
    {
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
		if (!$projectid)
            return '';
        $context = [
            'datatrics_projectid' => $projectid,
            'datatrics_product' => null,
            'datatrics_category' => null,
            'datatrics_user' => null,
            'datatrics_cart' => null
        ];
        if ($this->isProductPage()) {
            $context['datatrics_product'] = $this->getProductFromController($this->context->controller);
        }
        if ($this->isCategoryPage()) {
            $context['datatrics_category'] = $this->getCategoryFromController($this->context->controller);
        }
        if(!$this->isCartConformationPage() && $cart = $this->context->cart){
            if($cart->getOrderTotal() > 0){
                $context['datatrics_cart'] = [
                    'total' => $cart->getOrderTotal(),
                    'products' => $this->getProductsFromCart($cart)
                ];
            }
        }
        if($this->context->customer){
            if ($this->context->customer->id) {
                $context['datatrics_user'] = [
                    'id' => $this->context->customer->id,
                    'email' => $this->context->customer->email,
                    'firstname' => $this->context->customer->firstname,
                    'lastname' => $this->context->customer->lastname,
                ];
            }
        }
        $this->context->smarty->assign([
            'datatrics_projectid' => $context['datatrics_projectid'],
            'datatrics_product' => $context['datatrics_product'],
            'datatrics_category' => $context['datatrics_category'],
            'datatrics_user' => $context['datatrics_user'],
            'datatrics_cart' => $context['datatrics_cart']
        ]);
        return $this->display(__FILE__, 'header.tpl');
    }


    /**
     * Add the IMG for the Datatrics Tracker
     * 
     * @param array $params
     *  
     * @return string
     *
     */
	public function hookTop(array $params): string
	{
		$projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
		if (!$projectid)
			return '';
		$this->context->smarty->assign(array(
			'datatrics_projectid' => $projectid
		));
		return $this->display(__FILE__, 'noscript.tpl');
    }
    
    /**
     * Add conversion.
     * 
     * @param array $params
     *  
     * @return string
     */
    public function hookDisplayOrderConfirmation(array $params): string
    {
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
		if (!$projectid)
            return '';
        $context = [];
        $order_reference = $params['order']->reference;
        $order = $this->getOrderFromReference($order_reference);
        $cart  = $this->getCart($order->id_cart);
        $context['purchase'] = [
            'id'          => $order->id,
            'total'       => (float) number_format($order->total_paid_tax_incl, '2', '.', ''),
            'subtotal'    => (float) number_format(($order->total_paid_tax_incl-$order->total_shipping_tax_incl), '2', '.', ''),
            'tax'         => (float) number_format(($order->total_paid_tax_incl - $order->total_paid_tax_excl), '2', '.', ''),
            'shipping'    => (float) number_format($order->total_shipping_tax_incl, '2', '.', ''),
            'products'    => $this->getProductsFromCart($cart)
        ];
        $this->context->smarty->assign([
            'datatrics_order' => $context['purchase']
        ]);
        return $this->display(__FILE__, 'order_confirmation.tpl');
    }
    
    /**
     * Add purchase.
     * 
     * @param array $params
     *  
     * @return string
     */
    public function hookActionObjectOrderPaymentAddAfter(array $params): string
    {
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
		if (!$projectid)
            return '';
        $context = [];
        $order_reference = $params['object']->order_reference;
        $order = $this->getOrderFromReference($order_reference);
        $cart  = $this->getCart($order->id_cart);
        $context['purchase'] = [
            'id'          => $order->id,
            'total'       => (float) number_format($order->total_paid_tax_incl, '2', '.', ''),
            'subtotal'    => (float) number_format(($order->total_paid_tax_incl-$order->total_shipping_tax_incl), '2', '.', ''),
            'tax'         => (float) number_format(($order->total_paid_tax_incl - $order->total_paid_tax_excl), '2', '.', ''),
            'shipping'    => (float) number_format($order->total_shipping_tax_incl, '2', '.', ''),
            'products'    => $this->getProductsFromCart($cart)
        ];
        $this->context->smarty->assign([
            'datatrics_order' => $context['purchase']
        ]);
        return $this->display(__FILE__, 'order_confirmation.tpl');
    }

    /**
     * Wether or not public product page is currently loading.
     */
    protected function isProductPage(): bool
    {
        return 'product' === Dispatcher::getInstance()->getController();
    }

    /**
     * Wether or not public order conformation page is currently loading.
     */
    protected function isCartConformationPage(): bool
    {
        return 'orderconfirmation' === Dispatcher::getInstance()->getController();
    }
    
    /**
     * Wether or not public category page is currently loading.
     */
    protected function isCategoryPage(): bool
    {
        return 'category' === Dispatcher::getInstance()->getController();
    }

    /**
     * Get product from ProductController.
     * 
     * @param ProductController $controller
     * 
     * @return array
     */
    protected function getProductFromController(ProductController $controller): array
    {
        $product = $controller->getProduct();
        return [
            'id'       => $product->reference,
            'name'     => $product->name,
            'price'    => number_format($product->price * (1 + $product->tax_rate / 100), '2', '.', ''),
            'category' => $product->category ? : '',
        ];
        return [];

    }

    /**
     * Get category from CategoryController.
     * 
     * @param CategoryController $controller
     * 
     * @return array
     */
    protected function getCategoryFromController(CategoryController $controller): array
    {
        $category = $controller->getCategory();
        if($category->name){
            return [
                'name' => $category->name
            ];
        }
        return [];
    }

    /**
     * Get Order from reference.
     * 
     * @param string $reference
     * 
     * @return Order
     */
    protected function getOrderFromReference(string $reference): Order
    {
        return Order::getByReference($reference)->getFirst();
    }

    /**
     * Get Cart.
     * 
     * @param int $id_cart
     * 
     * @return Cart
     */
    protected function getCart(int $id_cart): Cart
    {
        return new Cart($id_cart);
    }

    /**
     * Get products from Cart.
     * 
     * @param Cart $cart
     * 
     * @return array
     */
    protected function getProductsFromCart(Cart $cart): array
    {
        return array_map(function ($product) {
            return [
                'id'       => $product['reference'],
                'name'     => $product['name'],
                'price'    => (float) number_format($product['price_with_reduction'], '2', '.', ''),
                'category' => $product['category'] ? : '',
                'quantity' => (int) $product['cart_quantity']
            ];
        }, $cart->getProducts());
    }
}
