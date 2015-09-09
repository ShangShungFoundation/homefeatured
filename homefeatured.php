<?php
/*
* 2007-2014 PrestaShop
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
*  @copyright  2007-2014 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit;

class HomeFeatured extends Module
{
	protected static $cache_products;

	public function __construct()
	{
		$this->name = 'homefeatured';
		$this->tab = 'front_office_features';
		$this->version = '1.6.4';
		$this->author = 'PrestaShop';
		$this->need_instance = 0;

		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l('Featured products on the homepage');
		$this->description = $this->l('Displays featured products in the central column of your homepage.');
	}

	public function install()
	{
		$this->_clearCache('*');
		Configuration::updateValue('HOME_FEATURED_NBR', 8);
		Configuration::updateValue('HOME_FEATURED_CAT', (int)Context::getContext()->shop->getCategory());
		Configuration::updateValue('HOME_FEATURED_RANDOMIZE', false);

		if (!parent::install()
			|| !$this->registerHook('header')
			|| !$this->registerHook('addproduct')
			|| !$this->registerHook('updateproduct')
			|| !$this->registerHook('deleteproduct')
			|| !$this->registerHook('categoryUpdate')
			|| !$this->registerHook('displayHomeTab')
			|| !$this->registerHook('displayHomeTabContent')
		)
			return false;

		return true;
	}

	public function uninstall()
	{
		$this->_clearCache('*');

		return parent::uninstall();
	}

	public function getContent()
	{
		$output = '';
		$errors = array();
		if (Tools::isSubmit('submitHomeFeatured'))
		{
			$nbr = Tools::getValue('HOME_FEATURED_NBR');
			if (!Validate::isInt($nbr) || $nbr <= 0)
			$errors[] = $this->l('The number of products is invalid. Please enter a positive number.');

			$cat = Tools::getValue('HOME_FEATURED_CAT');
			if (!Validate::isInt($cat) || $cat <= 0)
				$errors[] = $this->l('The category ID is invalid. Please choose an existing category ID.');

			$rand = Tools::getValue('HOME_FEATURED_RANDOMIZE');
			if (!Validate::isBool($rand))
				$errors[] = $this->l('Invalid value for the "randomize" flag.');
			if (isset($errors) && count($errors))
				$output = $this->displayError(implode('<br />', $errors));
			else
			{
				Configuration::updateValue('HOME_FEATURED_NBR', (int)$nbr);
				Configuration::updateValue('HOME_FEATURED_CAT', (int)$cat);
				Configuration::updateValue('HOME_FEATURED_RANDOMIZE', (bool)$rand);
				Tools::clearCache(Context::getContext()->smarty, $this->getTemplatePath('homefeatured.tpl'));
				$output = $this->displayConfirmation($this->l('Your settings have been updated.'));
			}
		}

		return $output.$this->renderForm();
	}

	public function hookDisplayHeader($params)
	{
		$this->hookHeader($params);
	}

	public function hookHeader($params)
	{
		if (isset($this->context->controller->php_self) && $this->context->controller->php_self == 'index')
			$this->context->controller->addCSS(_THEME_CSS_DIR_.'product_list.css');
		$this->context->controller->addCSS(($this->_path).'css/homefeatured.css', 'all');
	}

	public function _cacheProducts()
	{
		$lang_id = (int)Context::getContext()->language->id;
		if (!isset(HomeFeatured::$cache_products[$lang_id]))
		{
			$home_category = (int)Configuration::get('HOME_FEATURED_CAT');
			$nb = (int)Configuration::get('HOME_FEATURED_NBR');
			$lang_category = getLangCategories($lang_id);
			$categories = [$home_category, $lang_category];
			if (Configuration::get('HOME_FEATURED_RANDOMIZE'))
				HomeFeatured::$cache_products[$lang_id] = getProducts($categories, $lang_id, 1, ($nb ? $nb : 8), null, null, false, true, true, ($nb ? $nb : 8));
			else
				HomeFeatured::$cache_products[$lang_id] = getProducts($categories, $lang_id, 1, ($nb ? $nb : 8), 'position');
		}

		if (HomeFeatured::$cache_products[$lang_id] === false || empty(HomeFeatured::$cache_products[$lang_id]))
			return false;
	}

	public function hookDisplayHomeTab($params)
	{
		if (!$this->isCached('tab.tpl', $this->getCacheId('homefeatured-tab')))
			$this->_cacheProducts();

		return $this->display(__FILE__, 'tab.tpl', $this->getCacheId('homefeatured-tab'));
	}

	public function hookDisplayHome($params)
	{
		if (!$this->isCached('homefeatured.tpl', $this->getCacheId()))
		{
			$this->_cacheProducts();
			$this->smarty->assign(
				array(
					'products' => HomeFeatured::$cache_products[$params["cart"]->id_lang],
					'add_prod_display' => Configuration::get('PS_ATTRIBUTE_CATEGORY_DISPLAY'),
					'homeSize' => Image::getSize(ImageType::getFormatedName('home')),
				)
			);
		}

		return $this->display(__FILE__, 'homefeatured.tpl', $this->getCacheId());
	}

	public function hookDisplayHomeTabContent($params)
	{
		return $this->hookDisplayHome($params);
	}

	public function hookAddProduct($params)
	{
		$this->_clearCache('*');
	}

	public function hookUpdateProduct($params)
	{
		$this->_clearCache('*');
	}

	public function hookDeleteProduct($params)
	{
		$this->_clearCache('*');
	}

	public function hookCategoryUpdate($params)
	{
		$this->_clearCache('*');
	}

	public function _clearCache($template, $cache_id = NULL, $compile_id = NULL)
	{
		parent::_clearCache('homefeatured.tpl');
		parent::_clearCache('tab.tpl', 'homefeatured-tab');
	}

	public function renderForm()
	{
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Settings'),
					'icon' => 'icon-cogs'
				),
				'description' => $this->l('To add products to your homepage, simply add them to the corresponding product category (default: "Home").'),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->l('Number of products to be displayed'),
						'name' => 'HOME_FEATURED_NBR',
						'class' => 'fixed-width-xs',
						'desc' => $this->l('Set the number of products that you would like to display on homepage (default: 8).'),
					),
					array(
						'type' => 'text',
						'label' => $this->l('Category from which to pick products to be displayed'),
						'name' => 'HOME_FEATURED_CAT',
						'class' => 'fixed-width-xs',
						'desc' => $this->l('Choose the category ID of the products that you would like to display on homepage (default: 2 for "Home").'),
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Randomly display featured products'),
						'name' => 'HOME_FEATURED_RANDOMIZE',
						'class' => 'fixed-width-xs',
						'desc' => $this->l('Enable if you wish the products to be displayed randomly (default: no).'),
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						),
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			),
		);

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();
		$helper->id = (int)Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitHomeFeatured';
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
			'HOME_FEATURED_NBR' => Tools::getValue('HOME_FEATURED_NBR', (int)Configuration::get('HOME_FEATURED_NBR')),
			'HOME_FEATURED_CAT' => Tools::getValue('HOME_FEATURED_CAT', (int)Configuration::get('HOME_FEATURED_CAT')),
			'HOME_FEATURED_RANDOMIZE' => Tools::getValue('HOME_FEATURED_RANDOMIZE', (bool)Configuration::get('HOME_FEATURED_RANDOMIZE')),
		);
	}
}


function getLangCategories($id_lang) 
{
    return ($id_lang == 2? 43:31);
}

/**
* Modified getProducts 
    *
    * @param array id categories
    * @param integer $id_lang Language ID
    * @param integer $p Page number
    * @param integer $n Number of products per page
    * @param boolean $get_total return the number of results instead of the results themself
    * @param boolean $active return only active products
    * @param boolean $random active a random filter for returned products
    * @param int $random_number_products number of products to return if random is activated
    * @param boolean $check_access set to false to return all products (even if customer hasn't access)
    * @return mixed Products or number of products
    
    
*/
function getProducts($ids_categories, $id_lang, $p, $n, $order_by = null, $order_way = null, $get_total = false, $active = true, $random = false, $random_number_products = 1, $check_access = true, Context $context = null)
{
    if (!$context)
        $context = Context::getContext();
    
    $front = true;
    if (!in_array($context->controller->controller_type, array('front', 'modulefront')))
        $front = false;
        
    if ($p < 1) $p = 1;

    if (empty($order_by))
        $order_by = 'position';
    else
        /* Fix for all modules which are now using lowercase values for 'orderBy' parameter */
        $order_by = strtolower($order_by);

    if (empty($order_way))
        $order_way = 'ASC';
        
    $order_by_prefix = false;
    if ($order_by == 'id_product' || $order_by == 'date_add' || $order_by == 'date_upd')
        $order_by_prefix = 'p';
    elseif ($order_by == 'name')
        $order_by_prefix = 'pl';
    elseif ($order_by == 'manufacturer')
    {
        $order_by_prefix = 'm';
        $order_by = 'name';
    }
    elseif ($order_by == 'position')
        $order_by_prefix = 'cp';

    if ($order_by == 'price')
        $order_by = 'orderprice';

    if (!Validate::isBool($active) || !Validate::isOrderBy($order_by) || !Validate::isOrderWay($order_way))
        die (Tools::displayError());

    $id_supplier = (int)Tools::getValue('id_supplier');
    
    $categories = implode(",",$ids_categories);

    $sql = 'SELECT p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity, MAX(product_attribute_shop.id_product_attribute) id_product_attribute, product_attribute_shop.minimal_quantity AS product_attribute_minimal_quantity, pl.`description`, pl.`description_short`, pl.`available_now`,
                pl.`available_later`, pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`, pl.`name`, MAX(image_shop.`id_image`) id_image,
                il.`legend`, m.`name` AS manufacturer_name, cl.`name` AS category_default,
                DATEDIFF(product_shop.`date_add`, DATE_SUB(NOW(),
                INTERVAL '.(Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20).'
                    DAY)) > 0 AS new, product_shop.price AS orderprice
            FROM `'._DB_PREFIX_.'category_product` cp
            LEFT JOIN `'._DB_PREFIX_.'product` p
                ON p.`id_product` = cp.`id_product`
            '.Shop::addSqlAssociation('product', 'p').'
            LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa
            ON (p.`id_product` = pa.`id_product`)
            '.Shop::addSqlAssociation('product_attribute', 'pa', false, 'product_attribute_shop.`default_on` = 1').'
            '.Product::sqlStock('p', 'product_attribute_shop', false, $context->shop).'
            LEFT JOIN `'._DB_PREFIX_.'category_lang` cl
                ON (product_shop.`id_category_default` = cl.`id_category`
                AND cl.`id_lang` = '.(int)$id_lang.Shop::addSqlRestrictionOnLang('cl').')
            LEFT JOIN `'._DB_PREFIX_.'product_lang` pl
                ON (p.`id_product` = pl.`id_product`
                AND pl.`id_lang` = '.(int)$id_lang.Shop::addSqlRestrictionOnLang('pl').')
            LEFT JOIN `'._DB_PREFIX_.'image` i
                ON (i.`id_product` = p.`id_product`)'.
            Shop::addSqlAssociation('image', 'i', false, 'image_shop.cover=1').'
            LEFT JOIN `'._DB_PREFIX_.'image_lang` il
                ON (image_shop.`id_image` = il.`id_image`
                AND il.`id_lang` = '.(int)$id_lang.')
            LEFT JOIN `'._DB_PREFIX_.'manufacturer` m
                ON m.`id_manufacturer` = p.`id_manufacturer`
            WHERE product_shop.`id_shop` = '.(int)$context->shop->id.'
                AND cp.`id_category` IN ('.$categories.')'
                .($active ? ' AND product_shop.`active` = 1' : '')
                .($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '')
                .($id_supplier ? ' AND p.id_supplier = '.(int)$id_supplier : '')
                .' GROUP BY cp.id_product HAVING COUNT(*) >= 2';

    if ($random === true)
        $sql .= ' ORDER BY RAND() LIMIT '.(int)$random_number_products;
    else
        $sql .= ' ORDER BY '.(!empty($order_by_prefix) ? $order_by_prefix.'.' : '').'`'.bqSQL($order_by).'` '.pSQL($order_way).'
        LIMIT '.(((int)$p - 1) * (int)$n).','.(int)$n;

    $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    if ($order_by == 'orderprice')
        Tools::orderbyPrice($result, $order_way);

    if (!$result)
        return array();

    /* Modify SQL result */
    return Product::getProductsProperties($id_lang, $result);
}
