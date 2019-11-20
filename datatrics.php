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

require_once dirname(__FILE__) . '/vendor/autoload.php';

class Datatrics extends Module
{
    /**
     * Hooks for this module
     *
     * @var array
     */
    public $hooks = array(
        'displayHeader',
        'displayOrderConfirmation',
        'actionObjectOrderPaymentAddAfter',
        'actionObjectUpdateAfter',
        'actionObjectDeleteAfter',
        'actionObjectAddAfter',
        'actionCustomerAccountAdd',
        'actionCustomerAccountUpdate',
        'actionObjectCustomerAddAfter',
        'actionOrderStatusUpdate',
        'actionValidateOrder',
        'actionProductUpdate',
        'actionProductAdd',
        'actionProductDelete',
        'actionCategoryAdd',
        'actionCategoryDelete',
        'actionCategoryUpdate',
        'top',
    );

    /**
     * Projectid
     *
     * @var int
     */
    public $projectid;

    /**
     * API Key
     *
     * @var string
     */
    public $apikey;

    /**
     * tracker
     *
     * @var bool
     */
    public $tracker;

    /**
     * Sync
     *
     * @var bool
     */
    public $sync;

    /**
     * Is version 1.6?
     *
     * @var bool
     */
    public $isPrestashop16;

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
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'Datatrics B.V.';
        $this->need_instance = 0;

        $config = Configuration::getMultiple(
            array(
                'DATATRICS_PROJECTID',
                'DATATRICS_APIKEY',
                'DATATRICS_TRACKER',
                'DATATRICS_SYNC',
            )
        );
        if (isset($config['DATATRICS_PROJECTID'])) {
            $this->projectid = $config['DATATRICS_PROJECTID'];
        }
        if (isset($config['DATATRICS_APIKEY'])) {
            $this->apikey = $config['DATATRICS_APIKEY'];
        }
        if (isset($config['DATATRICS_TRACKER'])) {
            $this->tracker = $config['DATATRICS_TRACKER'];
        }
        if (isset($config['DATATRICS_SYNC'])) {
            $this->sync = $config['DATATRICS_SYNC'];
        }

        /*
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Datatrics');
        $this->description = $this->l('Datatrics intergration for Prestashop');

        $this->confirmUninstall = $this->l(
            'Are you sure you want to uninstall the intergration with Datatrics'
        );

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->isPrestashop16 = version_compare(_PS_VERSION_, '1.7.0.0', '<');

        if (!isset($this->projectid) || empty($this->projectid)) {
            $this->warning = $this->l(
                'The "Project ID" and "API Key" fields must be configured before using this module.'
            );
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
        if (!parent::install()) {
            $this->_errors[] = $this->l('There was an error during the installation.');

            return false;
        }

        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        foreach ($this->hooks as $hook) {
            $this->registerHook($hook);
        }
        if (!$this->isPrestashop16) {
            $this->registerHook('actionFrontControllerSetVariables');
        }
        Configuration::updateValue('DATATRICS_TRACKER', true);
        Configuration::updateValue('DATATRICS_SYNC', true);

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
        if (!parent::uninstall()) {
            $this->_errors[] = $this->l('There was an error during the uninstall.');

            return false;
        }

        foreach ($this->hooks as $hook) {
            $this->unregisterHook($hook);
        }
        if (!$this->isPrestashop16) {
            $this->unregisterHook('actionFrontControllerSetVariables');
        }

        Configuration::deleteByName('DATATRICS_PROJECTID');
        Configuration::deleteByName('DATATRICS_APIKEY');
        Configuration::deleteByName('DATATRICS_TRACKER');
        Configuration::deleteByName('DATATRICS_SYNC');

        include dirname(__FILE__) . '/sql/uninstall.php';

        return true;
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
        /*
         * If values have been submitted in the form, process.
         */
        if (((bool) Tools::isSubmit('submitDatatricsModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output . $this->renderForm();
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
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
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
                        'label' => $this->l('Tracker'),
                        'name' => 'DATATRICS_TRACKER',
                        'is_bool' => true,
                        'desc' => $this->l('Enable or disable the Datatrics Tracker.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'desc' => $this->l('You will find your Project ID in your Project on Datatrics'),
                        'name' => 'DATATRICS_PROJECTID',
                        'label' => $this->l('Project ID'),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Sync'),
                        'name' => 'DATATRICS_SYNC',
                        'is_bool' => true,
                        'desc' => $this->l('Enable or disable the Datatrics synchronisation.'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ),
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'DATATRICS_APIKEY',
                        'desc' => $this->l('You will find your API Key in your Account settings on Datatrics'),
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
            'DATATRICS_PROJECTID' => Configuration::get('DATATRICS_PROJECTID', null),
            'DATATRICS_APIKEY' => Configuration::get('DATATRICS_APIKEY', null),
            'DATATRICS_TRACKER' => Configuration::get('DATATRICS_TRACKER', null),
            'DATATRICS_SYNC' => Configuration::get('DATATRICS_SYNC', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
     * Define frontend variables
     *
     * @return array
     */
    public function hookActionFrontControllerSetVariables()
    {
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return [];
        }
        $tracker = Tools::safeOutput(Configuration::get('DATATRICS_TRACKER'));
        if (!$tracker) {
            return [];
        }
        $context = [
            'projectid' => $projectid,
            'product' => null,
            'category' => null,
            'user' => null,
            'cart' => null,
        ];
        if ($this->isProductPage()) {
            $context['product'] = $this->getProductFromController($this->context->controller);
        }
        if ($this->isCategoryPage()) {
            $context['category'] = $this->getCategoryFromController($this->context->controller);
        }
        if (!$this->isPrestashop16 && !$this->isCartConformationPage() && $cart = $this->context->cart) {
            if ($cart->getOrderTotal() > 0) {
                $context['cart'] = [
                    'total' => $cart->getOrderTotal(),
                    'products' => $this->getProductsFromCart($cart),
                ];
            }
        }
        if ($this->context->customer && $this->context->customer->isLogged()) {
            $context['user'] = [
                'id' => $this->context->customer->id,
            ];
            if (!$this->isPrestashop16) {
                $context['user']['email'] = $this->context->customer->email;
                $context['user']['firstname'] = $this->context->customer->firstname;
                $context['user']['lastname'] = $this->context->customer->lastname;
            }
        }
        if (!$this->isPrestashop16) {
            return [
                'projectid' => $context['projectid'],
                'source' => 'Prestashop-' . $this->context->shop->id,
                'product' => $context['product'],
                'category' => $context['category'],
                'customer' => $context['user'],
            ];
        }

        return ['datatarics' => $context];
    }

    /**
     * @return string
     *
     * Add the JavaScript for the Datatrics Tracker
     */
    public function hookDisplayHeader(): string
    {
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return '';
        }
        $tracker = Tools::safeOutput(Configuration::get('DATATRICS_TRACKER'));
        if (!$tracker) {
            return '';
        }
        if (!$this->isPrestashop16) {
            return $this->display(__FILE__, 'headerv2.tpl');
        }
        $context = $this->hookActionFrontControllerSetVariables();
        $jsonContext = [];
        foreach ($context['datatrics'] as $key => $value) {
            if (is_array($value)) {
                $value = Tools::jsonEncode($value);
            }
            $jsonContext[$key] = $value;
        }
        $this->context->smarty->assign([
            'datatrics' => $jsonContext,
        ]);

        return $this->display(__FILE__, 'header.tpl');
    }

    /**
     * Add the IMG for the Datatrics Tracker
     *
     * @param array $params
     *
     * @return string
     */
    public function hookTop(array $params): string
    {
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return '';
        }
        $tracker = Tools::safeOutput(Configuration::get('DATATRICS_TRACKER'));
        if (!$tracker) {
            return '';
        }
        $this->context->smarty->assign(array(
            'datatrics_projectid' => $projectid,
        ));

        return $this->display(__FILE__, 'noscript.tpl');
    }

    /**
     * Add conversion on confirmation page.
     *
     * @param array $params
     *
     * @return string
     */
    public function hookDisplayOrderConfirmation(array $params): string
    {
        if (!isset($params['object'])) {
            return '';
        }
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return '';
        }
        $order_reference = $params['object']->order_reference;
        $order = $this->getOrderFromReference($order_reference);
        $cart = $this->getCart($order->id_cart);
        $conversion = $this->buildApiConversion($order, $cart);
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if ($apikey && $sync) {
            $client = new Datatrics\API\Client($apikey, $projectid);
            try {
                $client->Sale->Create($conversion);
            } catch (\Exception $e) {
                PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
            }
        }
        $tracker = Tools::safeOutput(Configuration::get('DATATRICS_TRACKER'));
        if (!$tracker) {
            return '';
        }
        $this->context->smarty->assign([
            'datatrics_order' => Tools::jsonEncode($conversion),
        ]);

        return $this->display(__FILE__, 'order_confirmation.tpl');
    }

    /**
     * Add purchase after payment.
     *
     * @param array $params
     *
     * @return string
     */
    public function hookActionObjectOrderPaymentAddAfter(array $params): string
    {
        if (!isset($params['object'])) {
            return '';
        }
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return '';
        }
        $order_reference = $params['object']->order_reference;
        $order = $this->getOrderFromReference($order_reference);
        $cart = $this->getCart($order->id_cart);
        $conversion = $this->buildApiConversion($order, $cart);
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if ($apikey && $sync) {
            $client = new Datatrics\API\Client($apikey, $projectid);
            try {
                $client->Sale->Create($conversion);
            } catch (\Exception $e) {
                PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
            }
        }
        $tracker = Tools::safeOutput(Configuration::get('DATATRICS_TRACKER'));
        if (!$tracker) {
            return '';
        }
        $this->context->smarty->assign([
            'datatrics_order' => $conversion,
        ]);

        return $this->display(__FILE__, 'order_confirmation.tpl');
    }

    /**
     * Sync the order after a status update.
     *
     * @param $params
     */
    public function hookActionOrderStatusUpdate($params): void
    {
        if (!isset($params['id_order'])) {
            return;
        }
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        $order = new Order($params['id_order'], $this->context->language->id);
        $conversion = $this->buildApiConversion($order);
        $client = new Datatrics\API\Client($apikey, $projectid);
        try {
            $client->Sale->Create($conversion);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
        }
    }

    /**
     * Sync the order after validate.
     *
     * @param $params
     */
    public function hookActionValidateOrder($params): void
    {
        if (!isset($params['order'])) {
            return;
        }
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        $order = new Order($params['order']->id, $this->context->language->id);
        $conversion = $this->buildApiConversion($order);
        $client = new Datatrics\API\Client($apikey, $projectid);
        try {
            $client->Sale->Create($conversion);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
        }
    }

    /**
     * Sync the product after update.
     *
     * @param $params
     */
    public function hookActionProductUpdate(array $params): void
    {
        if (!isset($params['product'])) {
            return;
        }
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        try {
            $contentItems = $this->buildApiProduct($params['product']);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());

            return;
        }
        $client = new Datatrics\API\Client($apikey, $projectid);
        foreach ($contentItems as $contentItem) {
            $_contentItems = [];
            if (isset($contentItem['item']['variants'])) {
                if (count($contentItem['item']['variants'])) {
                    $_contentItems = $contentItem['item']['variants'];
                }
                unset($contentItem['item']['variants']);
            }
            $_contentItems[] = $contentItem;
            try {
                $client->Content->Bulk($_contentItems);
            } catch (\Exception $e) {
                PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
            }
        }
    }

    /**
     * Sync the product after add.
     *
     * @param $params
     */
    public function hookActionProductAdd($params): void
    {
        if (!isset($params['product'])) {
            return;
        }
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        try {
            $contentItems = $this->buildApiProduct($params['product']);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());

            return;
        }
        $client = new Datatrics\API\Client($apikey, $projectid);
        foreach ($contentItems as $contentItem) {
            $_contentItems = [];
            if (isset($contentItem['item']['variants'])) {
                if (count($contentItem['item']['variants'])) {
                    $_contentItems = $contentItem['item']['variants'];
                }
                unset($contentItem['item']['variants']);
            }
            $_contentItems[] = $contentItem;
            try {
                $client->Content->Bulk($_contentItems);
            } catch (\Exception $e) {
                PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
            }
        }
    }

    /**
     * Sync the product after delete.
     *
     * @param $params
     */
    public function hookActionProductDelete($params): void
    {
        if (!isset($params['product'])) {
            return;
        }
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        $product = $params['product'];
        $client = new Datatrics\API\Client($apikey, $projectid);
        try {
            $client->Content->Delete($product->id);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
        }
    }

    /**
     * Sync the category after update
     *
     * @param $params
     */
    public function hookActionCategoryUpdate($params): void
    {
        if (!isset($params['category'])) {
            return;
        }
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        $category = $params['category'];
        try {
            $contentItem = $this->buildApiCategory($category);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());

            return;
        }
        $client = new Datatrics\API\Client($apikey, $projectid);
        try {
            $client->Content->Create($contentItem);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
        }
    }

    /**
     * Sync the category after add
     *
     * @param $params
     */
    public function hookActionCategoryAdd($params): void
    {
        if (!isset($params['product'])) {
            return;
        }
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        $category = $params['category'];
        try {
            $contentItem = $this->buildApiCategory($category);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());

            return;
        }
        $client = new Datatrics\API\Client($apikey, $projectid);
        try {
            $client->Content->Create($contentItem);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
        }
    }

    /**
     * Sync the category after delete
     *
     * @param $params
     */
    public function hookActionCategoryDelete($params): void
    {
        if (!isset($params['product'])) {
            return;
        }
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        $category = $params['category'];
        $client = new Datatrics\API\Client($apikey, $projectid);
        try {
            $client->Content->Delete($category->id, ['type' => 'category']);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
        }
    }

    /**
     * After action update.
     *
     * @param array $params
     */
    public function hookActionObjectUpdateAfter($params): void
    {
        if (!isset($params['object'])) {
            return;
        }
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        if (is_a($params['object'], 'CustomerCore')) {
            $customer = $params['object'];
            try {
                $profile = $this->buildApiProfile($customer);
            } catch (\Exception $e) {
                PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());

                return;
            }
            $client = new Datatrics\API\Client($apikey, $projectid);
            try {
                $client->Profile->Create($profile);
            } catch (\Exception $e) {
                PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
            }
        }
    }

    /**
     * After action delete.
     *
     * @param array $params
     */
    public function hookActionObjectDeleteAfter($params): void
    {
        if (!isset($params['object'])) {
            return;
        }
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        if (is_a($params['object'], 'CustomerCore')) {
            $customer = $params['object'];
            $client = new Datatrics\API\Client($apikey, $projectid);
            try {
                $client->Profile->Delete($customer->id);
            } catch (\Exception $e) {
                PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
            }
        }
    }

    /**
     * After action add.
     *
     * @param array $params
     */
    public function hookActionObjectAddAfter($params): void
    {
        if (!isset($params['object'])) {
            return;
        }
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        if (is_a($params['object'], 'CustomerCore')) {
            $customer = $params['object'];
            try {
                $profile = $this->buildApiProfile($customer);
            } catch (\Exception $e) {
                PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());

                return;
            }
            $client = new Datatrics\API\Client($apikey, $projectid);
            try {
                $client->Profile->Create($profile);
            } catch (\Exception $e) {
                PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
            }
        }
    }

    /**
     * Add customer.
     *
     * @param array $params
     */
    public function hookActionCustomerAccountAdd($params): void
    {
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        $customer = $params['newCustomer'];
        try {
            $profile = $this->buildApiProfile($customer);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());

            return;
        }
        $client = new Datatrics\API\Client($apikey, $projectid);
        try {
            $client->Profile->Create($profile);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
        }
    }

    /**
     * Update customer.
     *
     * @param array $params
     */
    public function hookActionCustomerAccountUpdate($params): void
    {
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        $customer = $params['newCustomer'];
        try {
            $profile = $this->buildApiProfile($customer);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());

            return;
        }
        $client = new Datatrics\API\Client($apikey, $projectid);
        try {
            $client->Profile->Create($profile);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
        }
    }

    /**
     * After customer add.
     *
     * @param array $params
     */
    public function hookActionObjectCustomerAddAfter($params): void
    {
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
        if (!$projectid) {
            return;
        }
        $apikey = Tools::safeOutput(Configuration::get('DATATRICS_APIKEY'));
        if (!$apikey) {
            return;
        }
        $sync = Tools::safeOutput(Configuration::get('DATATRICS_SYNC'));
        if (!$sync) {
            return;
        }
        $customer = $params['object'];
        try {
            $profile = $this->buildApiProfile($customer);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());

            return;
        }
        $client = new Datatrics\API\Client($apikey, $projectid);
        try {
            $client->Profile->Create($profile);
        } catch (\Exception $e) {
            PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
        }
    }

    private function buildApiProfile(Customer $customer): array
    {
        $profile = [
            'profileid' => (string) $customer->id,
            'source' => 'Prestashop-' . $this->context->shop->id,
            'profile' => [
                'created' => $customer->date_add,
                'updated' => $customer->date_upd,
                'email' => $customer->email,
                'firstname' => $customer->firstname,
                'lastname' => $customer->lastname,
                'dateofbirth' => $customer->birthday,
                'newsletter' => $customer->newsletter,
                'ip' => $customer->ip_registration_newsletter,
                'optin' => $customer->optin,
                'status' => $customer->active,
                'company' => $customer->company,
                'shop_id' => $customer->id_shop,
                'gender' => ($customer->id_gender == 2 ? 'female' : ($customer->id_gender == 1 ? 'male' : 'unknown')),
                'lang' => Language::getIsoById((int) $customer->id_lang),
            ],
        ];
        foreach ($customer->getSimpleAddresses() as $address) {
            $profile['profile']['city'] = $address['city'];
            $profile['profile']['country'] = $address['country'];
            $profile['profile']['street'] = $address['address1'];
            $profile['profile']['phone'] = $address['phone'];
            $profile['profile']['zip'] = $address['postcode'];
            $profile['profile']['region'] = $address['state'];
            $profile['profile']['company'] = $address['company'];
            break;
        }

        return $profile;
    }

    private function buildApiConversion(Order $order, Cart $cart = null): array
    {
        $customer = $order->getCustomer();
        $this->hookActionCustomerAccountUpdate(['newCustomer' => $customer]);
        $conversion = [
            'conversionid' => (string) $order->id,
            'source' => 'Prestashop-' . $order->id_shop,
            'conversion' => [
                'total' => (float) number_format($order->total_paid_tax_incl, '2', '.', ''),
                'subtotal' => (float) number_format(($order->total_products_wt), '2', '.', ''),
                'tax' => (float) number_format(
                    ($order->total_paid_tax_incl - $order->total_paid_tax_excl),
                    '2',
                    '.',
                    ''
                ),
                'shipping' => (float) number_format($order->total_shipping_tax_incl, '2', '.', ''),
                'discount' => (float) number_format($order->total_discounts_tax_incl, '2', '.', ''),
                'wrapping' => (float) number_format($order->total_wrapping_tax_incl, '2', '.', ''),
                'created' => $order->date_add,
                'updated' => $order->date_upd,
                'shop_id' => $order->id_shop,
                'state_id' => $order->current_state,
                'email' => $customer->email,
                'profileid' => $customer->id,
                'items' => [],
            ],
        ];
        $state = $order->getCurrentStateFull($this->context->language->id);
        if ($state) {
            $conversion['conversion']['state'] = $state['name'];
        }
        $currency = Currency::getCurrency($order->id_currency);
        if ($currency) {
            $conversion['conversion']['currency'] = $currency['iso_code'];
        }
        if ($order->getProductsDetail()) {
            foreach ($order->getProductsDetail() as $line) {
                $category = new \Category($line['id_category_default'], $this->context->language->id, $order->id_shop);
                $itemId = $line['product_id'];
                if (isset($line['product_attribute_id']) && !empty($line['product_attribute_id'])) {
                    $itemId .= '-' . $line['product_attribute_id'];
                }
                $conversion['conversion']['items'][] = [
                    'itemid' => (string) $itemId,
                    'name' => (string) $line['product_name'],
                    'price' => (float) number_format($line['total_price_tax_incl'], '2', '.', ''),
                    'category' => (string) $this->sanitizeLanguageFieldToString($category->name),
                    'quantity' => (int) $line['product_quantity'],
                ];
                $this->hookActionProductUpdate(['product' => new \Product($line['product_id'], true)]);
            }
        }
        if ($cart) {
            $conversion['conversion']['items'] = [];
            foreach ($this->getProductsFromCart($cart) as $line) {
                $conversion['conversion']['items'][] = [
                    'itemid' => (string) $line['id'],
                    'name' => (string) $line['name'],
                    'price' => (float) number_format($line['price'], '2', '.', ''),
                    'category' => (string) $line['category'],
                    'quantity' => (int) $line['quantity'],
                ];
            }
        }

        return $conversion;
    }

    private function buildApiProduct(Product $product): array
    {
        $contentItems = [];
        $languages = Language::getLanguages(true, $this->context->shop->id);
        foreach ($languages as $language) {
            $variants = $product->getAttributeCombinations($language['id_lang']);
            $link_category = \Category::getLinkRewrite($product->id_category_default, $language['id_lang']);
            $url = $this->context->link->getProductLink(
                $product,
                null,
                $link_category,
                $product->ean13,
                $language['id_lang'],
                $this->context->shop->id,
                $product->getDefaultIdProductAttribute()
            );
            $image = \Image::getCover($product->id);
            $imagePath = $this->context->link->getImageLink(
                $product->link_rewrite[$language['id_lang']],
                $image['id_image'],
                ImageType::getFormattedName('home')
            );
            $imagePaths = [];
            $productImages = \Image::getImages($language['id_lang'], $product->id);
            foreach ($productImages as $productImage) {
                $imagePaths[] = $this->context->link->getImageLink(
                    $product->link_rewrite[$language['id_lang']],
                    $productImage['id_image'],
                    ImageType::getFormattedName('home')
                );
            }
            $contentItem = [
                'itemid' => (string) $product->id,
                'source' => 'Prestashop-' . $this->context->shop->id . '-' . Tools::strtoupper($language['iso_code']),
                'type' => 'item',
                'itemtype' => 'product',
                'item' => [
                    'created' => $product->date_add,
                    'updated' => $product->date_upd,
                    'name' => $this->sanitizeLanguageFieldToString($product->name, $language['id_lang']),
                    'description' => $this->sanitizeLanguageFieldToString(
                        $product->description_short,
                        $language['id_lang']
                    ),
                    'content' => $this->sanitizeLanguageFieldToString(
                        $product->description,
                        $language['id_lang']
                    ),
                    'price' => $product->getPriceWithoutReduct(),
                    'sale_price' => $product->getPrice(),
                    'sku' => $product->reference,
                    'status' => (string) $product->active,
                    'upc' => $product->upc,
                    'isbn' => $product->isbn,
                    'ean' => $product->ean13,
                    'weight' => $product->weight,
                    'depth' => $product->depth,
                    'height' => $product->height,
                    'width' => $product->width,
                    'customizable' => $product->customizable,
                    'condition' => $product->condition,
                    'visibility' => $product->visibility,
                    'new' => $product->new,
                    'stock' => (int) \Product::getRealQuantity($product->id),
                    'url' => $url,
                    'image' => $imagePath,
                    'images' => $imagePaths,
                ],
            ];
            if ($product->id_category_default) {
                $category = new \Category(
                    $product->id_category_default,
                    $language['id_lang'],
                    $this->context->shop->id
                );
                $apiCategory = null;
                try {
                    $apiCategory = $this->buildApiCategory($category, $language['id_lang']);
                } catch (\Exception $e) {
                    PrestaShopLogger::addLog('[DATATRICS] :' . $e->getMessage());
                }
                if ($apiCategory) {
                    $contentItem['item']['categories'] = [
                        [
                            'categoryid' => $apiCategory['categoryid'],
                            'name' => $apiCategory['category']['name'],
                            'url' => $apiCategory['category']['url'],
                        ],
                    ];
                }
            }
            $variants_so_far = array();
            foreach ($variants as $variant) {
                if (!isset($variants_so_far[$variant['id_product_attribute']])) {
                    $image = \Image::getBestImageAttribute(
                        $this->context->shop->id,
                        $language['id_lang'],
                        $product->id,
                        $variant['id_product_attribute']
                    );
                    $variants_so_far[$variant['id_product_attribute']] = [];
                    $variants_so_far[$variant['id_product_attribute']]['name'] = $contentItem['item']['name'];
                    $variants_so_far[$variant['id_product_attribute']]['sku'] = null;
                    $variants_so_far[$variant['id_product_attribute']]['price'] = 0;
                    $variants_so_far[$variant['id_product_attribute']]['stock'] = $variant['quantity'];
                    $variants_so_far[$variant['id_product_attribute']]['price'] = Product::getPriceStatic(
                        $product->id,
                        true,
                        $variant['id_product_attribute']
                    );
                    if (isset($image['id_image'])) {
                        $imagePath = $this->context->link->getImageLink(
                            $product->link_rewrite[$language['id_lang']],
                            $product->id . '-' . $image['id_image'],
                            ImageType::getFormattedName('home')
                        );
                        $variants_so_far[$variant['id_product_attribute']]['image'] = $imagePath;
                    }
                }
                $variants_so_far[$variant['id_product_attribute']]['name'] .= ' (' . $variant['group_name'];
                $variants_so_far[$variant['id_product_attribute']]['name'] .= ': ' . $variant['attribute_name'] . ')';
            }
            $copy_contentItem = $contentItem;
            foreach ($variants_so_far as $key => $value) {
                $variantItem = $copy_contentItem;
                $variantItem['itemid'] = (string) $product->id . '-' . $key;
                $variantItem['item']['parent_id'] = (string) $product->id;
                $variantItem['item']['name'] = (string) $value['name'];
                $variantItem['item']['sku'] = (string) $value['sku'];
                $variantItem['item']['sale_price'] = $value['price'];
                $variantItem['item']['price'] = $value['price'];
                $variantItem['item']['stock'] = (string) $value['stock'];
                if (isset($value['image'])) {
                    $variantItem['item']['image'] = (string) $value['image'];
                }
                $contentItem['item']['variants'][] = $variantItem;
            }
            $contentItems[] = $contentItem;
        }

        return $contentItems;
    }

    /**
     * It's a good idea to store categories in a cache to prevent multiple and unnecessary DB calls
     *
     * @param $categoryId
     *
     * @return \Category
     */
    private function buildApiCategory(Category $category, $languageId = null)
    {
        if (!$languageId) {
            $languageId = $this->context->language->id;
        }
        $language = Language::getLanguage($languageId);
        $url = $this->context->link->getCategoryLink(
            $category->id,
            null,
            $language['id_lang'],
            null,
            $this->context->shop->id
        );
        $contentItem = [
            'categoryid' => (string) $category->id,
            'source' => 'Prestashop-' . $this->context->shop->id . '-' . Tools::strtoupper($language['iso_code']),
            'type' => 'category',
            'category' => [
                'name' => $this->sanitizeLanguageFieldToString($category->name, $language['id_lang']),
                'description' => $this->sanitizeLanguageFieldToString($category->description, $language['id_lang']),
                'url' => $url,
            ],
        ];

        return $contentItem;
    }

    /**
     * Formats a language field
     *
     * @param $field
     *
     * @return mixed
     */
    protected function sanitizeLanguageFieldToString($field, $languageId = null)
    {
        if (!$languageId) {
            $languageId = $this->context->language->id;
        }
        if (is_array($field)) {
            if (isset($field[$languageId])) {
                $field = $field[$languageId];
            } else {
                $field = current($field);
            }
        }

        return '' . (string) $field;
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
            'id' => $product->reference,
            'name' => $product->name,
            'price' => number_format($product->price * (1 + $product->tax_rate / 100), '2', '.', ''),
            'category' => $product->category ?: '',
        ];
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
        if ($category->name) {
            return [
                'name' => $category->name,
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
        $lines = [];
        foreach ($cart->getProducts() as $product) {
            $itemId = $product['id_product'];
            if (isset($product['id_product_attribute']) && !empty($product['id_product_attribute'])) {
                $itemId .= '-' . $product['id_product_attribute'];
            }
            $line = [
                'id' => (string) $itemId,
                'name' => (string) $product['name'],
                'price' => (float) number_format($product['price_with_reduction'], '2', '.', ''),
                'category' => (string) $product['category'] ?: '',
                'quantity' => (int) $product['cart_quantity'],
            ];
            $lines[] = $line;
        }

        return $lines;
    }
}
