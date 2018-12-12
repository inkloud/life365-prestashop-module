<?php
/**
* 2007-2018 PrestaShop
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
*  @author    Giancarlo Spadini <giancarlo@spadini.it>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit;

require_once('ProductImporter.php');
require_once('AccessoryImporter.php');

class Life365 extends Module
{
    private $c_html = '';
    private $c_api_url = 'https://api.life365.eu/v2.php';

    public function __construct()
    {
		$this->name = 'life365';
		$this->tab = 'quick_bulk_update';
		$this->version = '1.2.67';
		$this->author = 'Giancarlo Spadini';
		$this->need_instance = 1;
		$this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.8');
		$this->module_key = '17fe516516b4f12fb1d877a3600dbedc';

		parent::__construct();

		$this->displayName = $this->l('Life365/Inkloud dropshipping');
		$this->description = $this->l('Expand your shop. Start now selling over 10.000 products in dropshipping!');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
	}

	public function install()
	{
		if (parent::install() == false || !$this->installDB() || !$this->registerHooks())
			return false;

		Configuration::updateValue($this->name.'_import_current_category', 0);
		Configuration::updateValue('PS_ALLOW_HTML_IFRAME', 1);

		return true;
	}

	public function enable($forceAll = false)
	{
		if (!parent::enable($forceAll = false))
			return false;

		Configuration::updateValue('PS_ALLOW_HTML_IFRAME', 1);

		return true;
	}

	public function installDB()
	{
		$t_sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.$this->name.'_product` (
				  `id_product_external` int(10) unsigned NOT NULL,
				  `date_import` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				  `id_product_ps` int(10) unsigned NOT NULL,
				  `version` int(10) unsigned NOT NULL DEFAULT \'0\',
				  PRIMARY KEY (`id_product_external`),
				  UNIQUE KEY `id_product_ps_unique` (`id_product_ps`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8;';
		Db::getInstance()->execute($t_sql);
		$t_sql = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.$this->name.'_category` (
				  `id_category_external` int(10) unsigned NOT NULL,
				  `profit` decimal(5,2) NOT NULL DEFAULT \'50.00\',
				  `id_category_ps` int(10) unsigned NOT NULL,
				  PRIMARY KEY (`id_category_external`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8;';
		Db::getInstance()->execute($t_sql);

		return true;
	}

	public function uninstallDB()
	{
		Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.$this->name.'_product`');
		Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.$this->name.'_category`');

		return true;
	}

	public function uninstall()
	{
		if (!parent::uninstall() || !$this->uninstallDB() || !Configuration::deleteByName('LIFE365_NAME') || !$this->unregisterHook('displayBackOfficeHeader'))
			return false;

		return true;
	}

	private function registerHooks()
	{	
		return (
			$this->registerHook('backOfficeHome') 
			&& $this->registerHook('DisplayBackOfficeHeader')
			&& $this->registerHook('displayAdminOrderTabOrder')
		);
	}
	private function unregisterHooks()
	{
		return true;
/*		return (
			$this->registerHook('backOfficeHome')
			&& $this->registerHook('displayBackOfficeHeader')
		);
*/	}

	public function hookDisplayAdminOrderTabOrder($params)	{   
		$this->smarty->assign(array('order' => $params['order'],
		'dropship_link' => $this->_path.'ajax_importer.php',
		'dropship_order' => $params['order']->id,
		'dropship_token' => Tools::getAdminToken($this->name)
		));
	
		return $this->display(__FILE__, 'views/templates/hook/dropship.tpl');
	}

	public function hookbackOfficeHome($params)
	{
		return $this->display(__FILE__, 'views/templates/hook/life365.tpl');
	}
	
	public function hookDisplayBackOfficeHeader($params)
	{
		$this->context->controller->addCSS($this->_path.'views/css/life365.css', 'all');
    }

	public function getContent()
	{
		if (Tools::isSubmit($this->name.'_importer'))
			return $this->startImportAjax();

		if (Tools::isSubmit($this->name.'_action_cat_click'))
        {
            $managed_cat = Tools::getValue($this->name.'_cat_click');
			return $this->manageCats2($managed_cat);
        }

        if (Tools::isSubmit($this->name.'_manage_cats'))
			return $this->manageCats();

		if (Tools::isSubmit($this->name.'_action_cats_b'))
		{
			$cat1 = Tools::getValue($this->name.'_cat1');
			$cats_array = Tools::getValue($this->name.'_categories');
			foreach ($cats_array as $cat)
			{
				$profit = Tools::getValue('profit_'.$cat) * 100;
				$ps_cat = Tools::getValue('cat_ps_'.$cat);

				Db::getInstance()->delete($this->name.'_category', 'id_category_external = '.(int)$cat);
				Db::getInstance()->insert($this->name.'_category', array(
					'id_category_external' => (int)$cat,
					'profit'      => (int)$profit / 100,
					'id_category_ps' => (int)$ps_cat
				));
			}
			Configuration::updateValue($this->name.'_'.$cat1.'_categories', implode(',', Tools::getValue($this->name.'_categories')));
		}

		if (Tools::isSubmit($this->name.'_save_other_settings'))
		{
			Configuration::updateValue($this->name.'_sync_name', Tools::getValue($this->name.'_sync_name'));
			Configuration::updateValue($this->name.'_sync_short_desc', Tools::getValue($this->name.'_sync_short_desc'));
			Configuration::updateValue($this->name.'_sync_desc', Tools::getValue($this->name.'_sync_desc'));
			Configuration::updateValue($this->name.'_sync_price', Tools::getValue($this->name.'_sync_price'));
			Configuration::updateValue($this->name.'_sync_category', Tools::getValue($this->name.'_sync_category'));
			Configuration::updateValue($this->name.'_debug_mode', Tools::getValue($this->name.'_debug_mode'));
			Configuration::updateValue($this->name.'_import_method', Tools::getValue($this->name.'_import_method'));
			Configuration::updateValue($this->name.'_price_limit', Tools::getValue($this->name.'_price_limit'));
			Configuration::updateValue($this->name.'_parent_categories', Tools::getValue($this->name.'_parent_categories'));
		}

		if (Tools::isSubmit($this->name.'_submit'))
		{
			Configuration::updateValue($this->name.'_login', Tools::getValue($this->name.'_login'));
			Configuration::updateValue($this->name.'_password', Tools::getValue($this->name.'_password'));
			Configuration::updateValue($this->name.'_country', Tools::getValue($this->name.'_country'));
			Configuration::updateValue($this->name.'_default_category', Tools::getValue($this->name.'_default_category'));
			Configuration::updateValue($this->name.'_overhead', Tools::getValue($this->name.'_overhead'));
			Configuration::updateValue($this->name.'_default_tax_id', Tools::getValue($this->name.'_default_tax_id'));
		}
		$this->displayForm();
		return $this->c_html;
	}

	private function getCatetoryDepth($id_category)
	{
		$level_depth = Db::getInstance()->getValue('
			SELECT level_depth
			FROM '._DB_PREFIX_.'category
			WHERE id_category = '.(int)$id_category
		);

		return $level_depth;
	}

	private function displayTaxRules($id_selected = 0)
	{
		$result_html = '';

		$result_html .= '<option value="0"'.(($id_selected == 0) ? ' selected="selected"' : '').'>No Tax</option>';

		$result = TaxRulesGroup::getTaxRulesGroups(true);
		foreach ($result as $tax)
		{
			$result_html .= '<option value="'.$tax['id_tax_rules_group'].'"'.(($id_selected == $tax['id_tax_rules_group']) ? ' selected="selected"' : '').'>';
			$result_html .= $tax['name'];
			$result_html .= '</option>';
		}

		return $result_html;
	}

	private function siteURL()
	{
		if (isset($_SERVER['HTTPS']) &&
			($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
			isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
			$_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
		{
			$protocol = 'https://';
		}
		else
		{
			$protocol = 'http://';
		}
		$domainName = $_SERVER['HTTP_HOST'];

		return $protocol.$domainName;
	}

	private function displayCatetoriesChildren($id_parent, $id_selected = 1, $id_lang = 1)
	{
		$result_html = '';
		$result = Category::getChildren($id_parent, $id_lang, true);
		foreach ($result as $cat)
		{
			$level_depth = $this->getCatetoryDepth($cat['id_category']);
			$result_html .= '<option value="'.$cat['id_category'].'"'.(($id_selected == $cat['id_category']) ? ' selected="selected"' : '').'>';
			$result_html .= str_repeat('&nbsp;', $level_depth * 2).$cat['name'];
			$result_html .= '</option>';
			$result_html .= $this->displayCatetoriesChildren($cat['id_category'], $id_selected, $id_lang);
		}
		return $result_html;
	}

	private function displayExternalCatetories($root_category = 0)
	{
		// $root_category : point of the tree to start to display children categories
		// if $root_category = 0 then display all categories
		$selected_categories_array = explode(',', Configuration::get($this->name.'_'.$root_category.'_categories'));
		$available_cats = $this->availableCategories();
		$list_cat3 = '';
		$cats_html = '<table>';
		$cats_html .= '<tr>';
		$cats_html .= '<th>'.Tools::strtoupper($this->name).' '.$this->l('category').'</th>';
		$cats_html .= '<th>'.$this->l('Local category').'</th>';
		$cats_html .= '<th>'.$this->l('Profit').'</th>';
		$cats_html .= '</tr>';
		if (is_array($available_cats))
		{
			$cat2 = 0;
			foreach ($available_cats as $cat)
			{
				if ($root_category == 0 || $cat['Cat1'] == $root_category)
				{
					if ($cat2 != $cat['Cat2'])
					{
						$cats_html .= '<tr><td colspan="3">';
						$cats_html .= '<b>'.$cat['description1'].'::'.$cat['description2'].'</b>';
						$cats_html .= '</td></tr>';
						$cat2 = $cat['Cat2'];
					}
					$list_cat3 .= $cat['Cat3'].',';
					if (in_array($cat['Cat3'], $selected_categories_array))
						$cat_checked = ' checked';
					else
						$cat_checked = '';
									
					$sql = 'SELECT `profit`, `id_category_ps`
						FROM `'._DB_PREFIX_.$this->name.'_category`
						WHERE id_category_external = '.$cat['Cat3'];
					if ($row = Db::getInstance()->getRow($sql))
					{
						$id_cat_ps = $row['id_category_ps'];
						$profit = $row['profit'];
					}
					else
					{
						$id_cat_ps = Configuration::get($this->name.'_default_category');
						$profit = Configuration::get($this->name.'_overhead');
					}

					$cats_html .= '<tr>';
					$cats_html .= '<td>&nbsp;&nbsp;<input type="checkbox" name="'.$this->name.'_categories[]" value="'.$cat['Cat3'].'"'.$cat_checked.' />'.$cat['description3'].'</td>';
					//$cats_html .= '<td><select name="cat_ps_'.$cat['Cat3'].'">';
					$cats_html .= '<td><select name="cat_ps_'.$cat['Cat3'].'" class="children_cats_select" data-selected-id="'.$id_cat_ps.'">';
					//$cats_html .= $this->displayCatetoriesChildren(Configuration::get('PS_HOME_CATEGORY'), $id_cat_ps, Configuration::get('PS_LANG_DEFAULT'));
					$cats_html .= '</select></td>';
					$cats_html .= '<td>';
					$cats_html .= '<input type="number" step="0.01" value="'.$profit.'" name="profit_'.$cat['Cat3'].'" placeholder="'.$this->l('profit').'" />%';
					$cats_html .= '</td>';
					$cats_html .= '</tr>';
				}
			}
		}
		$cats_html .= '</table>';
		$cats_html .= '<input type="hidden" name="'.$this->name.'_cat1" value="'.$root_category.'" />';
		$cats_html .= '<input type="hidden" name="'.$this->name.'_list_cat3" value="'.$list_cat3.'" />';


		// START::Thank you to Alessandro Nanni
		$cats_html .= '<select style="display:none;" id="all_categories">';
		$cats_html .= $this->displayCatetoriesChildren(Configuration::get('PS_HOME_CATEGORY'), Configuration::get('PS_HOME_CATEGORY'), Configuration::get('PS_LANG_DEFAULT'));
		$cats_html .= '</select>';

		$cats_html .= '<script language="javascript">$(function() {		
				var all_cats = $(\'#all_categories\').html();

				$( ".children_cats_select" ).each(function() {
					var original_name = $( this ).attr(\'name\');
					var sel = $( this ).attr(\'data-selected-id\');

					$( this ).html(all_cats);

					$( this ).attr(\'name\', original_name);
					$( this ).val(sel);
				});
			});</script>';
		// END::Thank you to Alessandro Nanni

		return $cats_html;
	}

	private function displayForm()
	{
		// Get domain portion
		$myUrl = $this->siteURL();

		$cron_url = Tools::getHttpHost(true).__PS_BASE_URI__.'modules/'.$this->name.'/ajax_importer.php?action=cron&token='.Tools::getAdminToken($this->name);
		$this->c_html .= '
		<fieldset><legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Main settings').'</legend>
		  <form action="'.$_SERVER['REQUEST_URI'].'" method="post" id="'.$this->name.'_form_settings">
		  <label>'.$this->l('Select country').'</label>
		  <div class="margin-form">
			<select id="'.$this->name.'_country" name="'.$this->name.'_country">
				<option value="0">'.$this->l('-- Choose a country --').'</option>
				<option value="IT" '.(Configuration::get($this->name.'_country') == 'IT' ? 'selected="selected"' : '').'>Italy</option>
				<option value="PT" '.(Configuration::get($this->name.'_country') == 'PT' ? 'selected="selected"' : '').'>Portugal</option>
				<option value="ES" '.(Configuration::get($this->name.'_country') == 'ES' ? 'selected="selected"' : '').'>Spain</option>
			</select>
			<a href="#" onclick="javascript:window.open(\'https://\'+$(\'#'.$this->name.'_country\').attr(\'value\')+\'.life365.eu/registrazione.asp\', \'_blank\');">'.$this->l('Register new account').'</a>
		  </div>
		  <label>'.$this->l('Life365 Login').'</label>
		  <div class="margin-form">
			<input type="text" name="'.$this->name.'_login" id="'.$this->name.'_login" value="'.Configuration::get($this->name.'_login').'" />
		  </div>
		  <label>'.$this->l('Life365 Password').'</label>
		  <div class="margin-form">
		  	<table>
				<tr>
					<td>
						<input type="password" name="'.$this->name.'_password" id="'.$this->name.'_password" value="'.Configuration::get($this->name.'_password').'" />
					</td>
					<td><div id="res_logon"></div></td>
					<td>
						<a href="#" onclick="javascript:check_user_pwd($(\'#'.$this->name.'_login\').attr(\'value\'), $(\'#'.$this->name.'_password\').attr(\'value\'), $(\'#'.$this->name.'_country\').attr(\'value\'));">'.$this->l('Test').'</a>
					</td>
				</tr>
			</table>
		  </div>
		  <label>'.$this->l('Default mark-up rate %').'</label>
		  <div class="margin-form">
			<input type="number" step="0.01" name="'.$this->name.'_overhead" value="'.Configuration::get($this->name.'_overhead').'" />
		  </div>
		  <label>'.$this->l('Default destination category').'</label>
		  <div class="margin-form">
			<select id="'.$this->name.'_default_category" name="'.$this->name.'_default_category">
				<option value="1">'.$this->l('-- Choose a category --').'</option>
				'.$this->displayCatetoriesChildren(Configuration::get('PS_HOME_CATEGORY'), Configuration::get($this->name.'_default_category'), Configuration::get('PS_LANG_DEFAULT')).'
			<select>
		  </div>
		  <label>'.$this->l('Default tax').'</label>
		  <div class="margin-form">
			<select name="'.$this->name.'_default_tax_id">
			'.$this->displayTaxRules(Configuration::get($this->name.'_default_tax_id')).'
			</select>
		  </div>
		  <input type="submit" id="'.$this->name.'_submit" name="'.$this->name.'_submit" value="'.$this->l('Update settings').'" class="button" />
		  </form>
		  <br />
			<form action="'.$_SERVER['REQUEST_URI'].'" method="post" id="'.$this->name.'_action_manage_cats">
				<input type="submit" name="'.$this->name.'_manage_cats" value="'.$this->l('Manage categories ...').'" class="button" />
			</form>
		</fieldset>
		<br />
		<fieldset><legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Action').'</legend>
			<form action="'.$_SERVER['REQUEST_URI'].'" method="post" id="'.$this->name.'_action_import">
				<input type="submit" name="'.$this->name.'_importer" value="'.$this->l('Start import ...').'" class="button" />
			</form>
			<div class="clear"></div>
			<br />
			<div>
				<b>'.$this->l('Cron url').': </b>
				'.$cron_url.'<br>
				<font size="-2"><a href="https://www.easycron.com/?ref=70609" target="_blank">A free CRON scheduler</a></font>
			</div>
		</fieldset>
		<br />
		<fieldset><legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Optional settings').'</legend>
			<form action="'.$_SERVER['REQUEST_URI'].'" method="post" id="'.$this->name.'_action_other_settings'.'">
				<label>'.$this->l('Synchronize always').'</label>
				<div class="margin-form">
					<ul style="list-style-type:none;margin:0;padding:0;">
						<li><input type="checkbox" name="'.$this->name.'_sync_name'.'" '.(Configuration::get($this->name.'_sync_name') ? 'checked="checked"' : '').' /> '.$this->l('Product name').'</li>
						<li><input type="checkbox" name="'.$this->name.'_sync_short_desc'.'" '.(Configuration::get($this->name.'_sync_short_desc') ? 'checked="checked"' : '').' /> '.$this->l('Product short description').'</li>
						<li><input type="checkbox" name="'.$this->name.'_sync_desc'.'" '.(Configuration::get($this->name.'_sync_desc') ? 'checked="checked"' : '').' /> '.$this->l('Product description').'</li>
						<li><input type="checkbox" name="'.$this->name.'_sync_category'.'" '.(Configuration::get($this->name.'_sync_category') ? 'checked="checked"' : '').' /> '.$this->l('Reset association with local categories').'</li>
						<li><input type="checkbox" name="'.$this->name.'_sync_price'.'" '.(Configuration::get($this->name.'_sync_price') ? 'checked="checked"' : '').' /> '.$this->l('Product price').'</li>
					</ul>
				</div>
				<div class="clear"></div>
				<label>'.$this->l('Associate to parent categories').'</label>
				<div class="margin-form">
					<input type="checkbox" name="'.$this->name.'_parent_categories'.'" '.(Configuration::get($this->name.'_parent_categories') ? 'checked="checked"' : '').' /> '.$this->l('Connect products with all parent categories').'
				</div>
				<div class="clear"></div>
				<label>'.$this->l('Price limit').'</label>
				<div class="margin-form">
					<input type="checkbox" name="'.$this->name.'_price_limit'.'" '.(Configuration::get($this->name.'_price_limit') ? 'checked="checked"' : '').' /> '.$this->l('Limits the price not to exceed the street-price').'
				</div>
				<div class="clear"></div>
				<label>'.$this->l('Debug').'</label>
				<div class="margin-form">
					<input type="checkbox" name="'.$this->name.'_debug_mode'.'" '.(Configuration::get($this->name.'_debug_mode') ? 'checked="checked"' : '').' /> '.$this->l('Debug enabled').'
				</div>
				<div class="clear"></div>
				<label>'.$this->l('Import method').'</label>
				<div class="margin-form">
					<input type="radio" name="'.$this->name.'_import_method'.'" value="1" '.(Configuration::get($this->name.'_import_method') ? 'checked="checked" ' : '').'/>
					<label class="t" for="display_on">'.$this->l('Normal').'</label>
					<input type="radio" name="'.$this->name.'_import_method'.'" value="0" '.(!Configuration::get($this->name.'_import_method') ? 'checked="checked" ' : '').'/>
					<label class="t" for="display_off"> '.$this->l('Safe, but slow').'</label>
				</div>
				<input type="submit" name="'.$this->name.'_save_other_settings" value="'.$this->l('Save optional settings').'" class="button" />
			</form>
		</fieldset>
		<script>
			function check_user_pwd(user1, password1, country1) {
				if(country1=="0") {
					$("#res_logon").html("Select a country, please.");
				}
				else {
					$.ajaxSetup ({cache: false});					
					var loadUrl = "'.$myUrl.__PS_BASE_URI__.'modules/'.$this->name.'/ajax_importer.php?action=checkLogon&token='.Tools::getAdminToken($this->name).'";
					$("#res_logon").html("<img src=\''.Tools::getHttpHost(true).__PS_BASE_URI__.'img/loader.gif\' />");

					$.ajax({
						type: "POST",
						url: loadUrl,
						dataType: "html",
						async: true,
						data: {u: user1, p: password1, c: country1}
					}).done(function( msg ) {
						$("#res_logon").html(msg);
					});
				}
			};
		</script>
		';
	}

	private function getAccessToken()
	{
		$country_id = Configuration::get($this->name.'_country');
		$login = Configuration::get($this->name.'_login');
		$password = Configuration::get($this->name.'_password');
		$referer = $_SERVER['HTTP_HOST'];
		$user_app = "PrestaShop module ver: ".$this->version;

		if(function_exists('curl_init'))
		{
			$con = curl_init();
			$url = $this->c_api_url.'?f=getToken';
			$my_values = array('country_id' => $country_id, 'login' => $login, 'password' => $password, 'referer' => $referer, 'user_app' => $user_app);

			curl_setopt($con, CURLOPT_URL, $url);
			curl_setopt($con, CURLOPT_POST, true);
			curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
			curl_setopt($con, CURLOPT_HEADER, false);
			curl_setopt($con, CURLOPT_RETURNTRANSFER, true);

			$res_curl = curl_exec($con);
			curl_close($con);

			$res = Tools::jsonDecode($res_curl, true);

			if($res["response_code"] == "1") {
				$token = $res["response_detail"];
			}
			else {
				$token = false;
			}

			return $token;
		}
		else
			return false;
	}

	private function getRootCategories()
	{
		$available_cats = $this->availableCategories();
		$root_cats = array();
		
		if (is_array($available_cats))
		{
			$cat1 = 0;
			foreach ($available_cats as $cat)
			{
				if ($cat1 != $cat['Cat1'])
				{
					$new_cat = array('Cat1' => $cat['Cat1'], 'description1' => $cat['description1']);

					array_push($root_cats, $new_cat);
					$cat1 = $cat['Cat1'];
				}
			}
		}
		
		return $root_cats;
	}

	private function availableCategories()
	{
		$access_token = $this->getAccessToken();

		if(function_exists('curl_init'))
		{
			$con = curl_init();
			$url = $this->c_api_url.'?f=getCategories&access_token='.$access_token;
			$my_values = array();

			curl_setopt($con, CURLOPT_URL, $url);
			curl_setopt($con, CURLOPT_POST, true);
			curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
			curl_setopt($con, CURLOPT_HEADER, false);
			curl_setopt($con, CURLOPT_RETURNTRANSFER, true);

			$res_curl = curl_exec($con);
			curl_close($con);

			$res = Tools::jsonDecode($res_curl, true);

			if($res["response_code"] == "1") {
				return $res["response_detail"];
			}
			else {
				return false;
			}
		}
		else
			return false;
	}


   	private function manageCats()
	{
		$result_html = '';
		$root_cats = $this->getRootCategories();

		$result_html .= '<fieldset><legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Manage categories to import').'</legend>';
		if (is_array($root_cats))
		{
			$result_html .= '<div class="col-sm-5">';
			foreach ($root_cats as $cat)
			{
				$result_html .= '<div>
					<form action="'.$_SERVER['REQUEST_URI'].'" method="post" id="'.$this->name.'_action_cats-'.$cat["Cat1"].'">
						<input type="hidden" name="'.$this->name.'_cat_click" value="'.$cat["Cat1"].'" />
						<input type="submit" name="'.$this->name.'_action_cat_click" value="'.$this->l('Manage category '). $cat["description1"] . '" class="button" />
					</form></div>';
			}
			$result_html .= '</div>';

		}
		$result_html .= '
			</div>			
		</fieldset>';

		return $result_html;
	}


	private function manageCats2($managed_cat)
	{
		$result_html = '';
		$root_cats = $this->getRootCategories();

		$result_html .= '<fieldset><legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Manage categories to import').'</legend>';
		if (is_array($root_cats))
		{
			$result_html .= '
				<div id="tabs">
				  <ul>';
			foreach ($root_cats as $cat)
			{
                if ($cat["Cat1"] == $managed_cat)
                {
                    $root_cats_current = $cat;
                    $result_html .= '<li><a href="#tabs-'.$cat["Cat1"].'">'.$cat["description1"].'</a></li>';
                }
			}
			$result_html .= '</ul>';

            $cats_html = $this->displayExternalCatetories($managed_cat);

            $result_html .= '<div id="tabs-'.$cat["Cat1"].'">
                    <form action="'.$_SERVER['REQUEST_URI'].'" method="post" id="'.$this->name.'_action_cats-'.$cat["Cat1"].'">
                        <div class="margin-form">
                            '.$cats_html.'
                        </div>
                        <input class="button" type="button" onclick="history.back()" value="'.$this->l('Cancel').'"></input>
                        <input type="submit" name="'.$this->name.'_action_cats_b" value="'.$this->l('Update settings').'" class="button" />
                    </form>
                </div>';
		}
		$result_html .= '
			</div>
		</fieldset>';

		return $result_html;
	}


	private function startImportAjax()
	{
		if(Tools::strlen(Configuration::get($this->name.'_import_method')) == 0)
			Configuration::updateValue($this->name.'_import_method', 1);
		
		$result_html = '';

		$current_file_name = array_reverse(explode('/', $_SERVER['SCRIPT_NAME']));
		$cron_url_search = Tools::getHttpHost(true, true).__PS_BASE_URI__.
			Tools::substr($_SERVER['SCRIPT_NAME'], Tools::strlen(__PS_BASE_URI__), -Tools::strlen($current_file_name['0'])).
			'searchcron.php?full=1&token='.Tools::getAdminToken($this->name);
		$cron_url_search_img = Tools::getHttpHost(true).__PS_BASE_URI__.'img/questionmark.png';

//		$resume_category = Configuration::get($this->name.'_import_current_category');
		$root_cats = $this->getRootCategories();
		$result_html .= '
			<script>
			var todo_categories = {};
			var todo_categories_desc = {};
			</script>
		';

		$categories = array();
		foreach ($root_cats as $cat)
		{
			$categories[] = $cat["Cat1"];
			$selected_categories_array = Configuration::get($this->name.'_'.$cat["Cat1"].'_categories');
			$result_html .= '
			<fieldset><legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Action').'</legend>
				<div id="waiter_'.$cat["Cat1"].'"><img src="'.Tools::getHttpHost(true).__PS_BASE_URI__.'img/loader.gif" alt="loading..." /></div>
				<div id="result_'.$cat["Cat1"].'"></div>
			</fieldset>
			<script>
               todo_categories["'.$cat["Cat1"].'"] = ['.$selected_categories_array.'];
			   todo_categories_desc["'.$cat["Cat1"].'"] = "'.$cat["description1"].'";
			</script>
			';
		}

		$result_html .= '
			<script>
				function getProds1(k, loadUrl, selected_category, not_used, n_try)
                {
					var todo_cat = todo_categories[selected_category];
					for(g=0;g<todo_cat.length;g++)
					{
						$.ajaxSetup ({cache: false});
						$.ajax({
								type: "POST",
								url: loadUrl,
								dataType : "html",
								async: '.Configuration::get($this->name.'_import_method').',
								data: {cat: todo_cat[g], offset: k, qty: 20}
							}).done(function( msg ) {
								if (msg.length > 0 && k<10)
								{
									$("#result_"+selected_category).append(msg + "<br />");
									getProds1(k+1, loadUrl, selected_category, 0, 0);
								}
								else
								{
									console.log("END_Cat:"+todo_cat[g]+"Offset"+k+"Selected_category:"+selected_category);
									$("#result_"+selected_category).append(msg + "<br />");
								}
							})
							.fail(function (msg, textStatus, errorThrown) {
								$("#result_"+selected_category).append("ERROR: " + textStatus + " - " + errorThrown + "<br />");
								console.log(errorThrown);
							})
					}
                }

				function getProds0(k, loadUrl, selected_category, g, n_try)
                {
					var todo_cat = todo_categories[selected_category];
					if (g<todo_cat.length)
					{
						$.ajax({
								type: "POST",
								url: loadUrl,
								dataType : "html",
								async: true,
								data: {cat: todo_cat[g], offset: k, qty: 20}
							}).done(function( msg ) {
								if (msg.length > 0 && k<10)
								{
									console.log("cat:"+todo_cat[g]+"offset"+k+"selected_category:"+selected_category);
									$("#result_"+selected_category).append(msg + "<br />");
									getProds0(k+1, loadUrl, selected_category, g, 0);
								}
								else
								{
									console.log("END_Cat:"+todo_cat[g]+"Offset"+k+"Selected_category:"+selected_category);
									$("#result_"+selected_category).append(msg + "<br />");
									getProds0(0, loadUrl, selected_category, g+1, 0);
								}
							})
							.fail(function (msg, textStatus, errorThrown) {
								$("#result_"+selected_category).append("ERROR: " + textStatus + " - " + errorThrown + "<br />");
								console.log(errorThrown);
								if (n_try<5)
								{
									$("#result_"+selected_category).append("Retrying "+todo_cat[g]+" ("+n_try+") ...<br />");
									getProds0(k, loadUrl, selected_category, g, n_try+1);
								}
								else {
									getProds0(0, loadUrl, selected_category, g+1, 0);
								}
							})
					}
					else
					{
						console.log("waiter_"+selected_category);
						$("#waiter_"+selected_category).html("<b>Process completed!</b><br /><br />");
//		$("#waiter_"+selected_categories[i]).html("<b>Process completed!</b><br /><br />");
//		$("#result_"+selected_categories[i]).append("<br /><br /><a href=\"'.$cron_url_search.'\" target=\"_blank\"><img src=\"'.$cron_url_search_img.'\">Click here to create a search index for new products<img src=\"'.$cron_url_search_img.'\"></a><br />");
					}
                }

			$(document).ready(function() {
				var loadUrl = "'.$this->siteURL().__PS_BASE_URI__.'modules/'.$this->name.'/ajax_importer.php?action=getProds&token='.Tools::getAdminToken($this->name).'";

				var selected_categories = '.Tools::jsonEncode($categories).'

				for (var i=0;i<selected_categories.length;i++)
				{
					$("#result_"+selected_categories[i]).append("Job started. Category name <b>"+todo_categories_desc[selected_categories[i]]+"</b>, subcategory to import: <b>"+todo_categories[selected_categories[i]].length+"</b><br /><br />");
					getProds'.Configuration::get($this->name.'_import_method').'(0, loadUrl, selected_categories[i], 0);
				}
			});

			</script>
		';

		return $result_html;
	}

}
