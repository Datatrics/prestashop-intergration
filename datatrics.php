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
    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'datatrics';
        $this->tab = 'advertising_marketing';
        $this->version = '1.0.0';
        $this->author = 'Datatrics B.V.';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Datatrics');
        $this->description = $this->l('Datatrics intergration for Prestashop');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall the intergration with Datatrics');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {

        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        Configuration::updateValue('DATATRICS_LIVE_MODE', false);

        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('displayHeader') &&
            $this->registerHook('top');
    }

    public function uninstall()
    {
        Configuration::deleteByName('DATATRICS_LIVE_MODE');

        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
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
     * Create the structure of your form.
     */
    protected function getConfigForm()
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
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter your projectid'),
                        'name' => 'DATATRICS_PROJECTID',
                        'label' => $this->l('Email'),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'name' => 'DATATRICS_APIKEY',
                        'desc' => $this->l('Enter your apikey'),
                        'label' => $this->l('Password'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'DATATRICS_LIVE_MODE' => Configuration::get('DATATRICS_LIVE_MODE', true),
            'DATATRICS_PROJECTID' => Configuration::get('DATATRICS_PROJECTID', null),
            'DATATRICS_APIKEY' => Configuration::get('DATATRICS_APIKEY', null),
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
     * Add the JavaScript for the Datatrics Tracker
     * https://github.com/creativewave/ps-cwgecommerce/blob/master/cwgecommerce.php
     */
    public function hookDisplayHeader()
    {
        $projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
		if (!$projectid)
            return;
        $this->context->smarty->assign(array(
            'datatrics_projectid' => $projectid
        ));
        return $this->display(__FILE__, 'header.tpl');
    }


    /**
     * Add the IMG for the Datatrics Tracker
     */
	public function hookTop($params)
	{
		$projectid = Tools::safeOutput(Configuration::get('DATATRICS_PROJECTID'));
		if (!$projectid)
			return;
		$this->context->smarty->assign(array(
			'datatrics_projectid' => $projectid
		));
		return $this->display(__FILE__, 'noscript.tpl');
	}
}
