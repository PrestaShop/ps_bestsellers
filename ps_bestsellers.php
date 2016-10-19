<?php
/*
* 2007-2016 PrestaShop
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
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;
use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Core\Product\ProductListingPresenter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;

if (!defined('_PS_VERSION_'))
	exit;

class Ps_BestSellers extends Module implements WidgetInterface
{
	protected static $cache_best_sellers = array();

	public function __construct()
	{
		$this->name = 'ps_bestsellers';
		$this->tab = 'front_office_features';
		$this->version = '1.0.0';
		$this->author = 'PrestaShop';
		$this->need_instance = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0',
            'max' => _PS_VERSION_,
        ];

        $this->bootstrap = true;
        parent::__construct();

		$this->displayName = $this->getTranslator()->trans('Top-sellers block', array(), 'Module.BestSellers.Admin');
		$this->description = $this->getTranslator()->trans('Adds a block displaying your store\'s top-selling products.', array(), 'Module.BestSellers.Admin');
	}

	public function install()
	{
		$this->_clearCache('*');
        Configuration::updateValue('PS_BLOCK_BESTSELLERS_TO_DISPLAY', 10);

		return parent::install()
			&& $this->registerHook('displayRightColumn')
            && $this->registerHook('actionOrderStatusPostUpdate')
            && $this->registerHook('actionProductAdd')
            && $this->registerHook('actionProductUpdate')
            && $this->registerHook('actionProductDelete')
            && $this->registerHook('displayHome')
            && ProductSale::fillProductSales()
        ;
	}

	public function uninstall()
	{
		$this->_clearCache('*');

		return parent::uninstall();
	}

	public function hookActionProductAdd($params)
	{
		$this->_clearCache('*');
	}

	public function hookActionProductUpdate($params)
	{
		$this->_clearCache('*');
	}

	public function hookActionProductDelete($params)
	{
		$this->_clearCache('*');
	}

	public function hookActionOrderStatusPostUpdate($params)
	{
		$this->_clearCache('*');
	}

	public function _clearCache($template, $cache_id = null, $compile_id = null)
	{
	    self::$cache_best_sellers = array();
		parent::_clearCache('ps_bestsellers.tpl', 'ps_bestsellers');
	}

	/**
	 * Called in administration -> module -> configure
	 */
	public function getContent()
	{
		$output = '';
		if (Tools::isSubmit('submitBestSellers'))
		{
			Configuration::updateValue('PS_BLOCK_BESTSELLERS_DISPLAY', (int)Tools::getValue('PS_BLOCK_BESTSELLERS_DISPLAY'));
			Configuration::updateValue('PS_BLOCK_BESTSELLERS_TO_DISPLAY', (int)Tools::getValue('PS_BLOCK_BESTSELLERS_TO_DISPLAY'));
			$this->_clearCache('*');
			$output .= $this->displayConfirmation($this->l('Settings updated'));
		}

		return $output.$this->renderForm();
	}

	public function renderForm()
	{
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->getTranslator()->trans('Settings', array(), 'Modules.BestSellers.Admin'),
					'icon' => 'icon-cogs'
				),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->getTranslator()->trans('Products to display', array(), 'Modules.BestSellers.Admin'),
						'name' => 'PS_BLOCK_BESTSELLERS_TO_DISPLAY',
						'desc' => $this->getTranslator()->trans('Determine the number of product to display in this block', array(), 'Modules.BestSellers.Admin'),
						'class' => 'fixed-width-xs',
					),
					array(
						'type' => 'switch',
						'label' => $this->getTranslator()->trans('Always display this block', array(), 'Modules.BestSellers.Admin'),
						'name' => 'PS_BLOCK_BESTSELLERS_DISPLAY',
						'desc' => $this->getTranslator()->trans('Show the block even if no best sellers are available.', array(), 'Modules.BestSellers.Admin'),
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->getTranslator()->trans('Enabled', array(), 'Modules.BestSellers.Admin')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->getTranslator()->trans('Disabled', array(), 'Modules.BestSellers.Admin')
							)
						),
					)
				),
				'submit' => array(
					'title' => $this->getTranslator()->trans('Save', array(), 'Modules.BestSellers.Admin')
				)
			)
		);

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();

		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitBestSellers';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}

	public function getConfigFieldsValues()
	{
		return array(
			'PS_BLOCK_BESTSELLERS_TO_DISPLAY' => (int)Tools::getValue('PS_BLOCK_BESTSELLERS_TO_DISPLAY', Configuration::get('PS_BLOCK_BESTSELLERS_TO_DISPLAY')),
			'PS_BLOCK_BESTSELLERS_DISPLAY' => (int)Tools::getValue('PS_BLOCK_BESTSELLERS_DISPLAY', Configuration::get('PS_BLOCK_BESTSELLERS_DISPLAY')),
		);
	}

    public function renderWidget($hookName, array $configuration)
    {
        if (empty($this->getBestSellers()) && !Configuration::get('PS_BLOCK_BESTSELLERS_DISPLAY'))
            return;

        $this->smarty->assign($this->getWidgetVariables($hookName, $configuration));

        return $this->fetch(
            'module:ps_bestsellers/views/templates/hook/ps_bestsellers.tpl',
            $this->getCacheId('ps_bestsellers')
        );
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        return [
            'products' => $this->getBestSellers(),
        ];
    }

    protected function getBestSellers()
    {
        if (Configuration::get('PS_CATALOG_MODE'))
            return false;

        if (!empty(self::$cache_best_sellers)) {
            return self::$cache_best_sellers;
        }
        
        $context = Context::getContext();

        Context::getContext();
        if (!($result = ProductSale::getBestSalesLight($context->language->id, 0, (int)Configuration::get('PS_BLOCK_BESTSELLERS_TO_DISPLAY'))))
            return (Configuration::get('PS_BLOCK_BESTSELLERS_DISPLAY') ? array() : false);

        $currency = new Currency($context->currency->id);
        $usetax = (Product::getTaxCalculationMethod((int)$this->context->customer->id) != PS_TAX_EXC);
        foreach ($result as &$row)
            $row['price'] = Tools::displayPrice(Product::getPriceStatic((int)$row['id_product'], $usetax), $currency);

        $assembler = new ProductAssembler($this->context);

        $presenterFactory = new ProductPresenterFactory($this->context);
        $presentationSettings = $presenterFactory->getPresentationSettings();
        $presenter = new ProductListingPresenter(
            new ImageRetriever(
                $this->context->link
            ),
            $this->context->link,
            new PriceFormatter(),
            new ProductColorsRetriever(),
            $this->context->getTranslator()
        );

        $products_for_template = [];

        foreach ($result as $rawProduct) {
            $products_for_template[] = $presenter->present(
                $presentationSettings,
                $assembler->assembleProduct($rawProduct),
                $this->context->language
            );
        }

        return self::$cache_best_sellers = $products_for_template;
    }
}
