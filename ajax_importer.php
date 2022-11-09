<?php
/**
* 2007-2021 PrestaShop
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
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header("Access-Control-Allow-Headers: X-Requested-With");
ini_set('max_execution_time', 7200);

require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../classes/Cookie.php');

require_once(dirname(__FILE__).'/ProductImporter.php');
require_once(dirname(__FILE__).'/AccessoryImporter.php');

global $kernel;
if(!$kernel){ 
    require_once _PS_ROOT_DIR_.'/app/AppKernel.php';
    $kernel = new \AppKernel('prod', false);
    $kernel->boot(); 
}

$context = Context::getContext();

if (!function_exists('p')) {
    function p()
    {
        return call_user_func_array('dump', func_get_args());
    }
}

if (PHP_SAPI === 'cli') {
    $action = $argv[1];
    $action_token = $argv[2];
    $opt_cat = $argv[3];
} else {
    $action_token = Tools::getValue('token');
    $action = Tools::getValue('action');
    $opt_cat = Tools::getValue('cat');
}
$module_name = getModuleInfo('name');
if ($action_token != Tools::getAdminToken($module_name)) {
    die('Invalid token');
}

$emplo = new Employee(1);
$context->employee = $emplo;

switch ($action) {
    case 'checkLogon':
        print checkLogon();
        break;
    case 'dropship':
        dropship();
        break;
    case 'getProds':
        print getProds($opt_cat);
        break;
    case 'cron':
        print runCron();
        break;
    case 'cron2':
        $mc = (int)Tools::getValue('mc');
        print runCron3($mc);
        break;
    case 'cron3':
        $mc = (int)Tools::getValue('mc');
        print runCron3($mc);
        break;
    case 'disableProds':
        print setProductsDisabled2($opt_cat);
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
    $_api_url = 'https://api.life365.eu/v2.php';
    $user_app = 'PrestaShop module ver: 1.2.90';
    $api_url_jwt = 'https://api.life365.eu/v4/auth/?f=check';

    $e_commerce_url = array(
        'IT' => 'https://www.life365.eu',
        'PT' => 'https://www.life365.pt',
        'ES' => 'https://www.inkloud.es',
        'NL' => 'https://www.inkloud.eu',
        'CN' => 'https://www.inkloud.cn'
    );

    $api_url_new = array(
        'IT' => 'https://it2.life365.eu',
        'PT' => 'https://pt2.life365.eu',
        'ES' => 'https://es2.life365.eu',
        'NL' => 'https://nl2.life365.eu',
        'CN' => 'https://new.inkloud.cn'
    );

    $country_default = array(
        'IT' => 102,
        'PT' => 1,
        'ES' => 17,
        'NL' => 150,
        'CN' => 39
    );
    $region_default = array(
        'IT' => 1,
        'PT' => 1,
        'ES' => 1,
        'NL' => 19,
        'CN' => 1
    );

    $country_id = Configuration::get($module_name.'_country');

    switch ($info) {
        case 'api_url_new':
            $detail = $api_url_new[$country_id];
            break;
        case 'api_url':
            $detail = $_api_url;
            break;
        case 'e_ecommerce_url':
            $detail = $e_commerce_url[$country_id];
            break;
        case 'api_url_jwt':
            $detail = $api_url_jwt;
            break;
        case 'name':
            $detail = $module_name;
            break;
        case 'default_country_id':
            $detail = $country_default[$country_id];
            break;
        case 'default_region_id':
            $detail = $region_default[$country_id];
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
    $context = Context::getContext();
    $token_expire = rand(0, 200);

    if (isset($context->cookie->access_token) && !empty($context->cookie->access_token) && $token_expire > 1 && $token_expire < 1) {
        $token = $context->cookie->access_token;
    } else {
        $_api_url = getModuleInfo('api_url');
        $module_name = getModuleInfo('name');
        $user_app = getModuleInfo('user_app');

        $country_id = Configuration::get($module_name.'_country');
        $login = Configuration::get($module_name.'_login');
        $password = Configuration::get($module_name.'_password');
        $referer = $_SERVER['HTTP_HOST'];

        $con = curl_init();
        $url = $_api_url.'?f=getToken';
        $my_values = array(
            'country_id' => $country_id,
            'login' => $login,
            'password' => $password,
            'referer' => $referer,
            'user_app' => $user_app.' with CRON'
        );

        curl_setopt($con, CURLOPT_URL, $url);
        curl_setopt($con, CURLOPT_POST, true);
        curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
        curl_setopt($con, CURLOPT_HEADER, false);
        curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

        $res_curl = curl_exec($con);
        curl_close($con);

        $res = Tools::jsonDecode($res_curl, true);

        if ($res['response_code'] == '1') {
            $token = $res['response_detail'];
            $context->cookie->__set('access_token', $token);
        } else {
            $token = false;
        }
    }
    return $token;
}


function getAccessJWT()
{
    $module_name = getModuleInfo('name');

    $api_url_jwt = getModuleInfo('api_url_jwt');
    $country_id = Configuration::get($module_name.'_country');
    $login = Configuration::get($module_name.'_login');
    $password = Configuration::get($module_name.'_password');

    $con = curl_init();
    $url = $api_url_jwt;
    $my_values = array('country' => $country_id, 'login' => $login, 'password' => $password, 'role' => 'customer');

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_POST, true);
    curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    $res_code = curl_getinfo($con, CURLINFO_HTTP_CODE);
    curl_close($con);

    if ($res_code == 200) {
        $res = Tools::jsonDecode($res_curl, true);
        $jwt = $res['jwt'];
        return $jwt;
    } else {
        return 0;
    }
}


function getProducts2($category_id)
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();

    $debug = (bool)Configuration::get($module_name.'_debug_mode');
    
    $api_url_new = getModuleInfo('api_url_new');

    $con = curl_init();
    $url = $api_url_new."/api/products/level_3/".$category_id."?jwt=".$jwt;

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($retcode!=200) {
        $info = curl_getinfo($con);
        p($info);
        p($res_curl);
    }

    curl_close($con);

    $res = Tools::jsonDecode($res_curl, true);

    return $res;
}


function getSingleProduct($product_id)
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();

    $debug = (bool)Configuration::get($module_name.'_debug_mode');
    
    $api_url_new = getModuleInfo('api_url_new');

    $con = curl_init();
    $url = $api_url_new."/api/products/".$product_id."?jwt=".$jwt;

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($retcode!=200) {
        $info = curl_getinfo($con);
        p($info);
        p($res_curl);
    }

    curl_close($con);

    $res = Tools::jsonDecode($res_curl, true);

    return $res;
}


function getProductsDisabled2($category_id)
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();

    $debug = (bool)Configuration::get($module_name.'_debug_mode');
    
    $api_url_new = getModuleInfo('api_url_new');

    $con = curl_init();
    $url = $api_url_new."/api/warehouse/getDisabledProducts/".$category_id."?jwt=".$jwt;

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($retcode!=200) {
        $info = curl_getinfo($con);
        p($info);
        p($res_curl);
    }

    curl_close($con);

    $res = Tools::jsonDecode($res_curl, true);

    return $res;
}


function setProductsDisabled2($category_id)
{
    $result_html = '';
    
    if ($category_id > 0) {
        $products = getProductsDisabled2($category_id);
        if (!empty($products)) {
            if (array_filter($products)) {
                $result_html .= 'MACROCATEGORY ' . $category_id . ' - CLEANING PHASE<br />Disabling products:';
                foreach ($products as $product) {
                    if ($debug) {
                        p($product);
                    }
                    $result_html .= ' '.$product['id'];
                    $objectProduct = Tools::jsonDecode(Tools::jsonEncode($product), false);

                    $accessroyImport = new AccessoryImporter();
                    $accessroyImport->setProductSource($objectProduct);
                    $accessroyImport->disable();
                }
            }
        }
    }
    $result_html .= '<br />';

    return $result_html;
    
}


function availableCategories()
{
    $_api_url = getModuleInfo('api_url');

    $access_token = getAccessToken();

    if (function_exists('curl_init')) {
        $con = curl_init();
        $url = $_api_url.'?f=getCategories&access_token='.$access_token;
        $my_values = array();

        curl_setopt($con, CURLOPT_URL, $url);
        curl_setopt($con, CURLOPT_POST, true);
        curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
        curl_setopt($con, CURLOPT_HEADER, false);
        curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

        $res_curl = curl_exec($con);
        curl_close($con);

        $res = Tools::jsonDecode($res_curl, true);

        if ($res['response_code'] == '1') {
            return $res['response_detail'];
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function checkLogon()
{
    $jwt = getAccessJWT();

    if (Tools::strlen($jwt) > 1) {
        return "<img src='".dirname($_SERVER['PHP_SELF']).'/../../'."img/admin/enabled.gif' /><font color='green'>Ok</font>";
    }
    else {
        return "<img src='".dirname($_SERVER['PHP_SELF']).'/../../'."img/admin/disabled.gif' /><font color='red'>".$res['response_text'].'</font>';
    }
}

function getProds($opt_cat = 0)
{
    $module_name = getModuleInfo('name');

    $debug = (bool)Configuration::get($module_name.'_debug_mode');
    $offset = Tools::getValue('offset');
    $qty = Tools::getValue('qty');
    $country_l = Tools::strtolower(Configuration::get($module_name.'_country'));
    $macro_cat = 0;

    if ($opt_cat == 0) {
        $cat = Tools::getValue('cat');
    } else {
        $cat = $opt_cat;
    }

    if ($offset > 0) {
        return '';
    }

    $result_html = '';
    if (array_filter($products = getProducts2($cat))) {
        $result_html .= 'CATEGORY '.$cat.': IMPORT offset '.$offset.'<br />';
        $serviceAccessoryImport = new AccessoryImporter();
        foreach ($products as $product) {
            if ($debug) {
                p($product);
            }
            $objectProduct = Tools::jsonDecode(Tools::jsonEncode($product), false);
            $macro_cat = $objectProduct->level_1;

            if($objectProduct->level_3 != $cat){
                $result_html .= 'Skip product '.$product['id'].' not native category ('. $objectProduct->level_3 . ')<br />';
                continue;
            }

			$result_html .= 'Set quantity product '.$product['id'].' '.$product['code_simple'].' '.$product['last_update'].'<br />';
			$accessroyImport = new AccessoryImporter();
			$accessroyImport->saveQuantity($product['id'],$product['stock']);

            if($serviceAccessoryImport->getVersion($objectProduct->id) >= $objectProduct->last_update) {
                $result_html .='Skip product '.$product['id'].' latest version already <br />';
                continue;
            }
            $result_html .='Importing product '.$product['id'].'<br />';

            //convert to old format
            $objectProduct->reference = $objectProduct->code_simple;
            $objectProduct->name = $objectProduct->title->{$country_l};
            $objectProduct->meta_keywords = $objectProduct->keywords;
            $objectProduct->price = $objectProduct->price->price;
            $objectProduct->street_price = $objectProduct->price_a;

            $not_allowed_tag = array( 'iframe', 'script');
            $descriptionCleaned = preg_replace( '#<(' . implode( '|', $not_allowed_tag) . ').*>.*?</\1>#s', '', $objectProduct->descr->{$country_l});
            $objectProduct->description = $descriptionCleaned;

            $objectProduct->quantity = $objectProduct->stock;
            $objectProduct->url_image = json_decode(json_encode($objectProduct->photos), true)[0];
            $objectProduct->local_category = $objectProduct->level_3;
            $objectProduct->meta_description = '';
            $objectProduct->meta_title = $objectProduct->name;
            $objectProduct->short_description = 'Sizes: '.$objectProduct->dimensions.'<br>Box: '.$objectProduct->qty_box.'<br>Color: '.$objectProduct->color.'<br>Certificate: '.$objectProduct->certificate.'<br>Comp. brand: '.$objectProduct->brand;
            $objectProduct->version = $objectProduct->last_update;
            $objectProduct->id_manufactuter = $serviceAccessoryImport->getManufacturerId($objectProduct->brand);
            $objectProduct->manufactuter = $objectProduct->brand;
            $objectProduct->ean13 = $objectProduct->barcode;

            $accessroyImport->setProductSource($objectProduct);
            $accessroyImport->save();
        }
    }

    return $result_html;
}

function getCatStock($category_id)
{
    $name = getModuleInfo('name');
    $login = Configuration::get($name.'_login');
    $password = Configuration::get($name.'_password');

    $file = getModuleInfo('e_ecommerce_url')."/api/utils/csvdata/prodstock?v=2&l=".$login."&p=".$password."&idcat=".$category_id;

    $fileData = fopen($file,'r');

    //create header array id,code,stock,version_data
    $line = fgetcsv($fileData,0,";"); //get the first line
    $header = [];
    foreach($line as $val) {
        $header[] = $val;
    }

    $cats_array = explode(",", Configuration::get($name.'_'.$category_id.'_categories'));
    $i = 0;
    $result = [];
    while (($line = fgetcsv($fileData,0,";")) !== FALSE) {
        if($i == 0){$i += 1;
            continue; //skip the header line
        }
        $new_entry = [$header[0] => $line[0], $header[1] => $line[1] , $header[2] => $line[2], $header[3] => $line[3], $header[4] => $line[4], $header[5] => $line[5] ];
        if(in_array($new_entry['level_3'], $cats_array))
            $result[] = $new_entry;
    }

    return $result;
}


function runCron3($macro_cat)
{
    $module_name = getModuleInfo('name');
    $country_l = Tools::strtolower(Configuration::get($module_name.'_country'));

    $result_html = '';

    p('Section: '.$macro_cat.'<br />');

    if (Tools::strlen($macro_cat)>0) {
        $offset = 0;
        $products = getCatStock($macro_cat);
        while (array_filter($products) && $offset<1) {
            p('CATEGORY '.$macro_cat.': IMPORT offset '.$offset.'<br />');
            foreach ($products as $product) {
                p('Set quantity product '.$product['id'].' '.$product['code'].' '.$product['version_data']);
                $accessroyImport = new AccessoryImporter();
                $accessroyImport->saveQuantity($product['id'],$product['stock']);
                if($accessroyImport->getVersion($product['id']) >= $product['version_data']) {
                    p('Skip product '.$product['id'].' latest version already');
                    continue;
                }
                p('Importing product '.$product['id']);

                $all_product_data = getSingleProduct($product['id']);
                $objectProduct = Tools::jsonDecode(Tools::jsonEncode($all_product_data), false);
                $objectProduct->reference = $objectProduct->code_simple;
                $objectProduct->name = $objectProduct->title->{$country_l};
                $objectProduct->meta_keywords = $objectProduct->keywords;
                $objectProduct->price = $objectProduct->price->price;
                $objectProduct->street_price = $objectProduct->price_a;
                $objectProduct->description = strip_unsafe($objectProduct->descr->{$country_l}, $img=false);
                $objectProduct->quantity = $objectProduct->stock;
                $objectProduct->url_image = json_decode(json_encode($objectProduct->photos), true)[0];
                $objectProduct->local_category = $objectProduct->level_3;
                $objectProduct->meta_description = '';
                $objectProduct->meta_title = $objectProduct->name;
                $objectProduct->short_description = 'Sizes: '.$objectProduct->dimensions.'<br>Box: '.$objectProduct->qty_box.'<br>Color: '.$objectProduct->color.'<br>Certificate: '.$objectProduct->certificate.'<br>Comp. brand: '.$objectProduct->brand;
                $objectProduct->version = $objectProduct->last_update;
                $objectProduct->id_manufactuter = $accessroyImport->getManufacturerId($objectProduct->brand);
                $objectProduct->manufactuter = $objectProduct->brand;
                $objectProduct->ean13 = $objectProduct->barcode;

                $accessroyImport->setProductSource($objectProduct);
                $accessroyImport->save();
            }
            $offset += 1;
        }

        p("Starting periodic cleaning of obsolete products.");
        $result_html .= setProductsDisabled2($macro_cat);
    }

    return $result_html;
}


function getRootCategories()
{
    $available_cats = availableCategories();
    $root_cats = array();

    if (is_array($available_cats)) {
        $cat1 = 0;
        foreach ($available_cats as $cat) {
            if ($cat1 != $cat['Cat1']) {
                $new_cat = array('Cat1' => $cat['Cat1'], 'description1' => $cat['description1']);

                array_push($root_cats, $new_cat);
                $cat1 = $cat['Cat1'];
            }
        }
    }

    return $root_cats;
}


function runCron()
{
    $module_name = getModuleInfo('name');
    $country_l = Tools::strtolower(Configuration::get($module_name.'_country'));

    $result_html = '';

    $root_cats = getRootCategories();
    foreach ($root_cats as $root_cat)
        $result_html = runCron3($root_cat);

    return $result_html;
}


function dropship()
{
    $module_name = getModuleInfo('name');
    $debug = (bool)Configuration::get($module_name.'_debug_mode');

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
    $dropship_address['destination_region'] = State::getNameById($address->id_state);

    $destination_phone = $address->phone;
//    $destination_phone_mobile = $address->phone_mobile;

    $dropship_address['destination_phone'] = $destination_phone.' '.$address->phone_mobile;

    if (_PS_VERSION_ >= '1.7.7.0') {
        $products = $cart->getProductsDetail();
    } else { //older versions
        $products = $cart->getProducts();
    }

    foreach ($products as $product) {
        addProductToCart($product['supplier_reference'], $product['product_quantity']);
        $new_drop_product = array('code' => $product['supplier_reference'], 'qty' => $product['product_quantity']);

        array_push($dropship_products, $new_drop_product);
    }

    if ($debug) {
        p($dropship_address);
    }
    setShippingAddress($dropship_address);

    $login = Configuration::get($module_name.'_login');
    $password = Configuration::get($module_name.'_password');
    $new_url = getModuleInfo('e_ecommerce_url')."/checkout?l=$login&p=$password";
    Tools::redirect($new_url);

    return true;
}

//restituisce il carrello attivo, quello meno recente se ce ne sono più di uno, ne crea uno nuovo se non ne esistono
function getActiveCart()
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();
    
    $debug = (bool)Configuration::get($module_name.'_debug_mode');
    
    $api_url_new = getModuleInfo('api_url_new');

    $con = curl_init();
    $url = $api_url_new."/api/order/cart?jwt=".$jwt;
    
    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);    

    $res_curl = curl_exec($con);
    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($retcode!=200) {
        $info = curl_getinfo($con);
        p($info);
        p($res_curl);
    }

    curl_close($con);

    $res = Tools::jsonDecode($res_curl, true);

    $cartId = 0;

    foreach ($res as $resCart) {
        if ($cartId == 0) {
            $cartId = $resCart['id'];
        } else {
            if ($resCart['id'] < $cartId) {
                $cartId = $resCart['id'];
            }
        }
    }

    if ($cartId == 0) {
        $cartId = getNewCart();
    }

    return $cartId;
}

//build a new cart on extrernal ecommerce
function getNewCart()
{
    $jwt = getAccessJWT();
    $api_url_new = getModuleInfo('api_url_new');
    $url = $api_url_new."/api/order/cart?jwt=".$jwt;

    $con = curl_init();

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-from-urlencoded"));
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($con, CURLOPT_POSTFIELDS, 1);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    curl_close($con);

    $res = Tools::jsonDecode($res_curl, true);

    return $res['id'];
}


function addProductToCart($code, $qty)
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();
    $cartId = getActiveCart();

    $debug = (bool)Configuration::get($module_name.'_debug_mode');

    $api_url_new = getModuleInfo('api_url_new');

    $con = curl_init();
    $url = $api_url_new."/api/order/cart/".$cartId."?jwt=".$jwt;

    $data = '{"type": "PUT_PRODUCT", "value": {"code": "' . $code . '", "qta": ' . $qty . '}}';

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($con, CURLOPT_POSTFIELDS, $data);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);

    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($debug) {
        if ($retcode!=200) {
            $info = curl_getinfo($con);
            p($info);
            p($res_curl);
        }
    }

    curl_close($con);
}


function setShippingAddress($dropship_address)
{
    $module_name = getModuleInfo('name');
    $countryNumber = countryStringToNumber($dropship_address['destination_country']);
    $regionNumber = regionStringToNumber($dropship_address['destination_region'], $dropship_address['destination_country']);
    $jwt = getAccessJWT();
    $cartId = getActiveCart();
    $api_url_new = getModuleInfo('api_url_new');

    $con = curl_init();
    $url = $api_url_new."/api/order/cart/".$cartId."?jwt=".$jwt;

    $debug = (bool)Configuration::get($module_name.'_debug_mode');

    $data =
        '{"type": "PUT_ADDR",
            "value": {"spedizione": {
                "nome": "'.$dropship_address['destination_firstname'].' '.$dropship_address['destination_lastname'].'",
                "ragione_sociale": "'.$dropship_address['destination_company'].'",
                "nazione": '.$countryNumber.',
                "provincia": '.$regionNumber.',
                "citta": "' . $dropship_address['destination_city'].'",
                "indirizzo": "' . $dropship_address['destination_address'] .'",
                "cap": "' . $dropship_address['destination_postcode'].'",
                "telefono": "'. $dropship_address['destination_phone'].'"
                }
            }
        }';

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($con, CURLOPT_POSTFIELDS, $data);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($debug) {
        if ($retcode!=200) {
            $info = curl_getinfo($con);
            p($info);
            p($res_curl);
        }
    }

    curl_close($con);

    //fare una nuova put per mettere il FLAG DROPSHIP a true
}


function countryStringToNumber($countryString)
{
    //prendo l'id della nazione da cui si sta richiedendo il servizio, passandolo come 'country' all'API
    $module_name = getModuleInfo('name');
    $url = "http://api.life365.eu/v4/utils/?f=getCountryList" ;
    $my_values = array('country' => Configuration::get($module_name.'_country'));

    $debug = (bool)Configuration::get($module_name.'_debug_mode');

    $con = curl_init();

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_POST, true);
    curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($retcode!=200) {
        $info = curl_getinfo($con);
        p($info);
        p($res_curl);
    }

    curl_close($con);

    $res = Tools::jsonDecode($res_curl, true);

    //ricerco fra i nomi delle nazioni e quando trovata restituisco il numero corrispondente
    foreach ($res as $countries) {
        if ($countries['name'] == $countryString) {
            return $countries['id'];
        }
    }
    foreach ($res as $countries) {
        if ($countries['name'] == Configuration::get($module_name.'_country')) {
            return $countries['id'];
        }
    }

    return getModuleInfo('default_country_id');
}


function regionStringToNumber($regionString, $countryString)
{
    $module_name = getModuleInfo('name');
    $url = "http://api.life365.eu/v4/utils/?f=getCityList";
    $country_id = Configuration::get($module_name.'_country');

    $debug = (bool)Configuration::get($module_name.'_debug_mode');

    // prendo l'id della nazione da cui si sta richiedendo il servizio, passandolo come 'country' all'API
    $my_values = array('country' => $country_id, 'selectedCountry' => countryStringToNumber($countryString));

    $con = curl_init();

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_POST, true);
    curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);

    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($retcode!=200) {
        $info = curl_getinfo($con);
        p($info);
        p($res_curl);
    }

    curl_close($con);
    
    $res = Tools::jsonDecode($res_curl, true);

    //ricerco fra i nomi delle nazioni e quando trovata restituisco il numero corrispondente
    foreach ($res as $regions) {
        if ($regions['name'] == $regionString) {
            return $regions['id'];
        }
    }

    return getModuleInfo('default_region_id');
}


function strip_unsafe($string, $img=false)
{
    // Unsafe HTML tags that members may abuse
    $unsafe=array(
    '/<iframe(.*?)<\/iframe>/is',
    '/<title(.*?)<\/title>/is',
    '/<pre(.*?)<\/pre>/is',
    '/<frame(.*?)<\/frame>/is',
    '/<frameset(.*?)<\/frameset>/is',
    '/<object(.*?)<\/object>/is',
    '/<script(.*?)<\/script>/is',
    '/<embed(.*?)<\/embed>/is',
    '/<applet(.*?)<\/applet>/is',
    '/<meta(.*?)>/is',
    '/<!doctype(.*?)>/is',
    '/<link(.*?)>/is',
    '/<body(.*?)>/is',
    '/<\/body>/is',
    '/<head(.*?)>/is',
    '/<\/head>/is',
    '/onclick="(.*?)"/is',
    '/onload="(.*?)"/is',
    '/onunload="(.*?)"/is',
    '/<html(.*?)>/is',
    '/<\/html>/is');

    // Remove graphic too if the user wants
    if ($img==true)
    {
        $unsafe[]='/<img(.*?)>/is';
    }

    // Remove these tags and all parameters within them
    $string=preg_replace($unsafe, "", $string);

    return $string;
}