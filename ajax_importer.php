<?php
/**
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
*  @author    Giancarlo Spadini <info@anewbattery.com>
*  @copyright 2007-2014 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

ini_set('max_execution_time', 7200);

include_once('../../config/config.inc.php');

require_once(dirname(__FILE__).'/ProductImporter.php');
require_once(dirname(__FILE__).'/AccessoryImporter.php');

$context = Context::getContext();

if (!function_exists('p'))
{
	function p() { 
		return call_user_func_array('dump', func_get_args());
	}
}

$module_name = getModuleInfo('name');
$action_token = Tools::getValue('token');
if ($action_token != Tools::getAdminToken($module_name))
	die('Invalid token');

$emplo = new Employee(1);
$context->employee = $emplo;

$action = Tools::getValue('action');
switch ($action)
{
	case 'checkLogon':
		print checkLogon();
			break;
	case 'dropship':
		dropship();
		break;
	case 'getProds':
		print getProds();
		break;
	case 'cron':
		print runCron();
		break;
	case 'version':
		print getModuleInfo('user_app');
		print '<br>';
		print getModuleInfo('ps_version');
		break;
	default:
		echo 'error';
}

function getModuleInfo($info)
{
	$module_name = 'life365';
	$_api_url = 'http://api.life365.eu/v2.php';
	$user_app = 'PrestaShop module ver: 1.2.61';

	switch ($info)
	{
		case 'api_url':
			$detail = $_api_url;
			break;
		case 'name':
			$detail = $module_name;
			break;
		case 'user_app':
			$detail = $user_app;
			break;
		case 'ps_version':
			$detail = 'PrestaShop ver: '._PS_VERSION_;
			break;
		default:
			$detail = '';
			break;
	}
	
	return $detail;
}

function getAccessToken()
{
	$_api_url = getModuleInfo('api_url');
	$module_name = getModuleInfo('name');
	$user_app = getModuleInfo('user_app');

	$country_id = Configuration::get($module_name.'_country');
	$login = Configuration::get($module_name.'_login');
	$password = Configuration::get($module_name.'_password');
	$referer = $_SERVER['HTTP_HOST'];

	$con = curl_init();
	$url = $_api_url.'?f=getToken';
	$my_values = array('country_id' => $country_id, 'login' => $login, 'password' => $password, 'referer' => $referer, 'user_app' => $user_app.' with CRON');

	curl_setopt($con, CURLOPT_URL, $url);
	curl_setopt($con, CURLOPT_POST, true);
	curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
	curl_setopt($con, CURLOPT_HEADER, false);
	curl_setopt($con, CURLOPT_RETURNTRANSFER, true);

	$res_curl = curl_exec($con);
	curl_close($con);

	$res = Tools::jsonDecode($res_curl, true);

	if ($res['response_code'] == '1')
		$token = $res['response_detail'];
	else
		$token = false;

	return $token;
}

function getProducts($category_id, $qty, $offset, $access_token)
{
	$_api_url = getModuleInfo('api_url');
	$module_name = getModuleInfo('name');

	$debug = (bool)Configuration::get($module_name.'_debug_mode');

	$con = curl_init();
	$url = $_api_url."?f=getProductsByCat&access_token=".$access_token;
	$my_values = array('category_id' => $category_id, 'qty' => $qty, 'offset' => $offset);

	curl_setopt($con, CURLOPT_URL, $url);
	curl_setopt($con, CURLOPT_POST, true);
	curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
	curl_setopt($con, CURLOPT_HEADER, false);
	curl_setopt($con, CURLOPT_RETURNTRANSFER, true);

	$res_curl = curl_exec($con);
	if ($debug)
		p($res_curl);

	$retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
	if ($retcode!=200)
	{
		$info = curl_getinfo($con);
		p($info);
		p($res_curl);
	}

	curl_close($con);
	
	$res = Tools::jsonDecode($res_curl, true);

	return $res['response_detail'];
}

function getProductsDisabled($category_id, $qty, $offset, $access_token)
{
	$_api_url = getModuleInfo('api_url');
	$module_name = getModuleInfo('name');

	$debug = (bool)Configuration::get($module_name.'_debug_mode');

	$con = curl_init();
	$url = $_api_url.'?f=getProductsDisabled&access_token='.$access_token;
	$my_values = array('category_id' => $category_id, 'qty' => $qty, 'offset' => $offset);

	curl_setopt($con, CURLOPT_URL, $url);
	curl_setopt($con, CURLOPT_POST, true);
	curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
	curl_setopt($con, CURLOPT_HEADER, false);
	curl_setopt($con, CURLOPT_RETURNTRANSFER, true);

	$res_curl = curl_exec($con);
	if ($debug)
		p($res_curl);

	$retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
	if ($retcode!=200)
	{
		$info = curl_getinfo($con);
		p($info);
		p($res_curl);
	}

	curl_close($con);
	
	$res = Tools::jsonDecode($res_curl, true);

	return $res['response_detail'];
}

function availableCategories()
{
	$_api_url = getModuleInfo('api_url');

	$access_token = getAccessToken();

	if (function_exists('curl_init'))
	{
		$con = curl_init();
		$url = $_api_url.'?f=getCategories&access_token='.$access_token;
		$my_values = array();

		curl_setopt($con, CURLOPT_URL, $url);
		curl_setopt($con, CURLOPT_POST, true);
		curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
		curl_setopt($con, CURLOPT_HEADER, false);
		curl_setopt($con, CURLOPT_RETURNTRANSFER, true);

		$res_curl = curl_exec($con);
		curl_close($con);

		$res = Tools::jsonDecode($res_curl, true);

		if ($res['response_code'] == '1')
			return $res['response_detail'];
		else
			return false;
	}
	else
		return false;
}

function checkLogon() {
	$_api_url = getModuleInfo('api_url');
	$user_app = getModuleInfo('user_app');

	$login = Tools::getValue('u'); 
	$password = Tools::getValue('p'); 
	$country_id = Tools::getValue('c');
	$referer = $_SERVER['HTTP_HOST'];
	
	$con = curl_init();
	$url = $_api_url.'?f=getToken';
	$my_values = array('country_id' => $country_id, 'login' => $login, 'password' => $password, 'referer' => $referer, 'user_app' => $user_app);

	curl_setopt($con, CURLOPT_URL, $url);
	curl_setopt($con, CURLOPT_POST, true);
	curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
	curl_setopt($con, CURLOPT_HEADER, false);
	curl_setopt($con, CURLOPT_RETURNTRANSFER, true);

	$res_curl = curl_exec($con);
	curl_close($con);

	$res = Tools::jsonDecode($res_curl, true);

	if ($res['response_code'] == '1')
		return "<img src='".dirname($_SERVER['PHP_SELF']).'/../../'."img/admin/enabled.gif' /><font color='green'>Ok</font>";

	return "<img src='".dirname($_SERVER['PHP_SELF']).'/../../'."img/admin/disabled.gif' /><font color='red'>".$res['response_text'].'</font>';
}

function getProds() {
	$module_name = getModuleInfo('name');

	$debug = (bool)Configuration::get($module_name.'_debug_mode');
	$cat = Tools::getValue('cat');
	$offset = Tools::getValue('offset');
	$qty = Tools::getValue('qty');

	$access_token = getAccessToken();
	$result_html = '';
	if (array_filter($products = getProducts($cat, $qty, $offset, $access_token)))
	{
		$result_html .= 'CATEGORY '.$cat.': IMPORT offset '.$offset.'<br />';
		foreach ($products as $product) {
			if ($debug)
				p($product);
			$result_html .= 'Importing product '.$product['id'].'<br />';
			$objectProduct = Tools::jsonDecode(Tools::jsonEncode($product), FALSE);

			$accessroyImport = new AccessoryImporter();
			$accessroyImport->SetProductSource($objectProduct);
			$accessroyImport->Save();
		}
	}

	if (array_filter($products = getProductsDisabled($cat, $qty, $offset, $access_token)))
	{
		$result_html .= 'CATEGORY '.$cat.': CLEANING offset '.$offset.'<br />';
		foreach ($products as $product) {
			if ($debug)
				p($product);
			$result_html .= 'Cleaning product '.$product['id'].'<br />';
			$objectProduct = Tools::jsonDecode(Tools::jsonEncode($product), FALSE);

			$accessroyImport = new AccessoryImporter();
			$accessroyImport->SetProductSource($objectProduct);
			$accessroyImport->disable();
			
		}
	}
	

	return $result_html;
}

function getRootCategories()
{
	$available_cats = availableCategories();
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

function runCron() {
	$module_name = getModuleInfo('name');

	$qty = 30;
	$result_html = '';

	$access_token = getAccessToken();

	$root_cats = getRootCategories();
	foreach ($root_cats as $root_cat)
	{
		$cats_array = explode(",", Configuration::get($module_name.'_'.$root_cat["Cat1"].'_categories'));
		foreach ($cats_array as $cat)
		{
			$result_html .= 'Section: '.$root_cat['description1'].'<br />';
			if (Tools::strlen($cat)>0)
			{
				$offset = 0;
				while(array_filter($proucts = getProducts($cat, $qty, $offset, $access_token)) and $offset<5)
				{
					$result_html .= 'CATEGORY '.$cat.': IMPORT offset '.$offset.'<br />';
					foreach ($proucts as $product)
					{
						$result_html .= 'Importing product '.$product['id'].'<br />';
						$objectProduct = Tools::jsonDecode(Tools::jsonEncode($product), FALSE);

						$accessroyImport = new AccessoryImporter();
						$accessroyImport->SetProductSource($objectProduct);
						$accessroyImport->Save();
					}
					$offset += 1;
				}
			}
		}
	}

	return $result_html;
}

function dropship() {
	$access_token = getAccessToken();
	
	$id_order = (int)Tools::getValue('id_o');
	$cart = new Order((int)$id_order);
	$address = new Address($cart->id_address_delivery);

	$dropship_address = array();
	$dropship_products = array();
	
	$dropship_address['destination_firstname'] = $address->firstname;
	$dropship_address['destination_lastname'] = $address->lastname;
	$dropship_address['destination_company'] = $address->company;
	$dropship_address['destination_address'] = $address->address1.' '.$address->address2;
	$dropship_address['destination_postcode'] = $address->postcode;
	$dropship_address['destination_country'] = $address->country;
	$dropship_address['destination_city'] = $address->city;
	
	$destination_phone = $address->phone;
	$destination_phone_mobile = $address->phone_mobile;

	$dropship_address['destination_phone'] = $address->phone_mobile;

	$products = $cart->getProducts();
	foreach ($products as $product)
	{
		$new_drop_product = array('code' => $product['supplier_reference'], 'qty' => $product['product_quantity']);

		array_push($dropship_products, $new_drop_product);
	}
	
	$res = setDropshipOrder($dropship_address, $dropship_products, $access_token);
	
	return $res;
}


function setDropshipOrder($dropship_address, $dropship_products, $access_token)
{
	$_api_url = getModuleInfo('api_url');
	$module_name = getModuleInfo('name');
	$country_id = Configuration::get($module_name.'_country');
	$login = Configuration::get($module_name.'_login');
	$password = Configuration::get($module_name.'_password');

	$access_token = getAccessToken();

	if (function_exists('curl_init'))
	{
		$con = curl_init();
		$url = $_api_url.'?f=setDropshipOrder&access_token='.$access_token;
		$my_values = array('dropship_address' => serialize($dropship_address), 'dropship_products' => serialize($dropship_products));

		curl_setopt($con, CURLOPT_URL, $url);
		curl_setopt($con, CURLOPT_POST, true);
		curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
		curl_setopt($con, CURLOPT_HEADER, false);
		curl_setopt($con, CURLOPT_RETURNTRANSFER, true);

		$res_curl = curl_exec($con);
		$res = Tools::jsonDecode($res_curl, true);
		curl_close($con);

		$new_url = "http://$country_id.life365.eu/_login.asp?L=$login&P=$password&url=http://$country_id.life365.eu/carrello.asp?drop_on=on";
		Tools::redirect($new_url);

		if($res['response_code'] == "1") {
			// echo $res['response_detail'];
			return true;
		}
		else {
			return false;
		}

	}
	else
		return false;
}