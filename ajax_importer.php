<?php
/**
 * 2007-2025 PrestaShop
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
 * @author    Giancarlo Spadini <giancarlo@spadini.it>
 * @copyright 2007-2025 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-Requested-With');
ini_set('max_execution_time', 7200);

require_once dirname(__FILE__) . '/../../config/config.inc.php';
require_once dirname(__FILE__) . '/../../classes/Cookie.php';

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/ProductImporter.php';
require_once dirname(__FILE__) . '/AccessoryImporter.php';

if (!isset($kernel)) {
    require_once _PS_ROOT_DIR_ . '/app/AppKernel.php';
    $kernel = new \AppKernel('prod', false);
    $kernel->boot();
}

$context = Context::getContext();

if (!function_exists('p')) {
    function p($msg) {
        echo $msg . "\n";
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
        echo checkLogon();
        break;
        
    case 'dropship':
        echo dropship();
        break;
        
    case 'getProds':
        echo getProds($opt_cat);
        break;
        
    case 'cron':
        echo runCron();
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
        echo setProductsDisabled2($opt_cat);
        break;
        
    case 'version':
        print getModuleInfo('user_app');
        print '<br />';
        print getModuleInfo('ps_version');        
        echo 'Life365 PrestaShop module version: 8.0.97';
        break;
        
    default:
        echo 'Invalid action';
}

function getModuleInfo($info)
{
    $module_name = 'life365';
    $user_app = 'PrestaShop module ver: 8.0.98';

    $e_commerce_url = [
        'IT' => 'https://www.life365.eu',
        'PT' => 'https://www.life365.pt',
        'ES' => 'https://www.inkloud.es',
        'NL' => 'https://www.inkloud.eu',
    ];

    $api_url_new = [
        'IT' => 'https://it2.life365.eu',
        'PT' => 'https://pt2.life365.eu',
        'ES' => 'https://es2.life365.eu',
        'NL' => 'https://nl2.life365.eu',
    ];

    $country_default = [
        'IT' => 102,
        'PT' => 1,
        'ES' => 17,
        'NL' => 150,
    ];
    $region_default = [
        'IT' => 1,
        'PT' => 1,
        'ES' => 1,
        'NL' => 19,
    ];

    $country_id = Configuration::get($module_name . '_country');
    $detail = '';

    switch ($info) {
        case 'api_url_new':
            $detail = $api_url_new[$country_id];
            break;

        case 'name':
            $detail = $module_name;
            break;

        case 'user_app':
            $detail = $user_app;
            break;

        case 'e_ecommerce_url':
            if (isset($e_commerce_url[$country_id])) {
                $detail = $e_commerce_url[$country_id];
            }
            break;

        case 'api_url_new':
            if (isset($api_url_new[$country_id])) {
                $detail = $api_url_new[$country_id];
            }
            break;

        case 'default_country_id':
            $detail = $country_default[$country_id];
            break;

        case 'default_region_id':
            $detail = $region_default[$country_id];

        case 'country_default':
            if (isset($country_default[$country_id])) {
                $detail = $country_default[$country_id];
            }
            break;

        case 'region_default':
            if (isset($region_default[$country_id])) {
                $detail = $region_default[$country_id];
            }
            break;
    }

    return $detail;
}

function getAccessJWT()
{
    $_api_url_new = getModuleInfo('api_url_new');
    $module_name = getModuleInfo('name');
    $user_app = getModuleInfo('user_app');

    $login = Configuration::get($module_name . '_login');
    $password = Configuration::get($module_name . '_password');
    $referer = $_SERVER['HTTP_HOST'];

    $con = curl_init();
    $url = $_api_url_new . '/api/auth/';
    $my_values = [
        'login' => $login,
        'password' => $password,
        'referer' => $referer,
        'user_app' => $user_app,
    ];

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_POST, true);
    curl_setopt($con, CURLOPT_POSTFIELDS, json_encode($my_values));
    curl_setopt($con, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    curl_close($con);
    $res = json_decode($res_curl, true);

    $token = '';
    if ($res && isset($res['jwt'])) {
        $token = $res['jwt'];
    }

    return $token;
}

function getAccessToken()
{
    $context = Context::getContext();
    $token_expire = rand(0, 1);

    if (isset($context->cookie->access_token) && !empty($context->cookie->access_token) && $token_expire > 1 && $token_expire < 1) {
        $token = $context->cookie->access_token;
    } else {
        $token = getAccessJWT();
    }

    return $token;
}

function getProducts2($category_id)
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();

    $debug = (bool)Configuration::get($module_name . '_debug_mode');
    
    $api_url_new = getModuleInfo('api_url_new');

    $con = curl_init();
    $url = $api_url_new . '/api/products/level_3/' . $category_id . '?jwt=' . $jwt;

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($retcode != 200) {
        $info = curl_getinfo($con);
        p($info);
        p($res_curl);
    }

    curl_close($con);
    $res = json_decode($res_curl, true);

    return $res;
}

function getSingleProduct($product_id)
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();

    $debug = (bool)Configuration::get($module_name . '_debug_mode');

    $api_url_new = getModuleInfo('api_url_new');

    $con = curl_init();
    $url = $api_url_new . '/api/products/' . $product_id . '?jwt=' . $jwt;

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($retcode != 200) {
        $info = curl_getinfo($con);
        p($info);
        p($res_curl);
    }

    curl_close($con);
    $res = json_decode($res_curl, true);

    return $res;
}

function getProductsDisabled2($category_id)
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();

    $debug = (bool)Configuration::get($module_name . '_debug_mode');

    $api_url_new = getModuleInfo('api_url_new');

    $con = curl_init();
    $url = $api_url_new . '/api/warehouse/getDisabledProducts/' . $category_id . '?jwt=' . $jwt;

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($retcode != 200) {
        $info = curl_getinfo($con);
        p($info);
        p($res_curl);
    }

    curl_close($con);
    $res = json_decode($res_curl, true);

    return $res;
}

function setProductsDisabled2($category_id)
{
    $module_name = getModuleInfo('name');
    $debug = (bool)Configuration::get($module_name . '_debug_mode');

    $result_html = '';

    if ($category_id > 0) {
        $products = getProductsDisabled2($category_id);
        if (!empty($products)) {
            $result_html .= 'MACROCATEGORY ' . $category_id . ' - CLEANING PHASE<br />Disabling products:';
            foreach ($products as $product) {
                if ($debug) {
                    p($product);
                }
                $result_html .= ' ' . $product['id'];
                $objectProduct = json_decode(json_encode($product), false);

                $accessroyImport = new AccessoryImporter();
                $accessroyImport->setProductSource($objectProduct);
                $accessroyImport->disable();
            }
        }
    }
    $result_html .= '<br />';

    return $result_html;
}

function availableCategories()
{
    $api_url_new = getModuleInfo('api_url_new');

    if (function_exists('curl_init')) {
        $con = curl_init();
        $url = $api_url_new . '/api/warehouse/categoriesTree';

        curl_setopt($con, CURLOPT_URL, $url);
        curl_setopt($con, CURLOPT_HEADER, false);
        curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);
    
        $res_curl = curl_exec($con);
        curl_close($con);

        $res = json_decode($res_curl, true);

        if ($res) {
            return $res;
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
        return '<img src="' . dirname($_SERVER['PHP_SELF']) . '/../../img/admin/enabled.gif" alt="enabled"/><font color="green">Ok</font>';
    } else {
        return '<img src="' . dirname($_SERVER['PHP_SELF']) . '/../../img/admin/disabled.gif" alt="disabled"/><font color="red">Error</font>';
    }
}

function getProds($opt_cat = 0)
{
    $module_name = getModuleInfo('name');
    $context = Context::getContext();

    $debug = (bool)Configuration::get($module_name . '_debug_mode');
    $offset = Tools::getValue('offset');
    $qty = Tools::getValue('qty');
    $country_l = Tools::strtolower(Configuration::get($module_name . '_country'));
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
        $result_html .= 'CATEGORY ' . $cat . ': IMPORT offset ' . $offset . '<br />';
        $serviceAccessoryImport = new AccessoryImporter();
        foreach ($products as $product) {
            if ($debug) {
                p($product);
            }
            $objectProduct = json_decode(json_encode($product), false);
            $macro_cat = $objectProduct->level_1;

            if ($objectProduct->level_3 != $cat) {
                $result_html .= 'Skip product ' . $product['id'] . ' not native category (' . $objectProduct->level_3 . ')<br />';
                continue;
            }

            $result_html .= 'Set quantity product ' . $product['id'] . ' ' . $product['code_simple'] . ' ' . $product['last_update'] . '<br />';
            $accessroyImport = new AccessoryImporter();
            $accessroyImport->saveQuantity($product['id'], $product['stock']);

            if ($serviceAccessoryImport->getVersion($objectProduct->id) >= $objectProduct->last_update) {
                $result_html .= 'Skip product ' . $product['id'] . ' latest version already <br />';
                continue;
            }
            $result_html .= 'Importing product ' . $product['id'] . '<br />';

            $objectProduct->reference = $objectProduct->code_simple;
            $objectProduct->name = $objectProduct->title->{$country_l};
            $objectProduct->meta_keywords = $objectProduct->keywords;
            $objectProduct->price = $objectProduct->price->price;
            $objectProduct->street_price = $objectProduct->price_a;

            $not_allowed_tag = ['iframe', 'script'];
            $descriptionCleaned = preg_replace('#<(' . implode('|', $not_allowed_tag) . ').*>.*?</\1>#s', '', $objectProduct->descr->{$country_l});
            $objectProduct->description = $descriptionCleaned;

            $objectProduct->quantity = $objectProduct->stock;
            $objectProduct->url_image = json_decode(json_encode($objectProduct->photos), true)[0];
            $objectProduct->local_category = $objectProduct->level_3;
            $objectProduct->meta_description = '';
            $objectProduct->meta_title = $objectProduct->name;
            $objectProduct->short_description = $context->getTranslator()->trans('Sizes') . ': ' . $objectProduct->dimensions . '<br>' . $context->getTranslator()->trans('Box') . ': ' . $objectProduct->qty_box . '<br>' . $context->getTranslator()->trans('Color') . ': ' . $objectProduct->color . '<br>' . $context->getTranslator()->trans('Certificate') . ': ' . $objectProduct->certificate . '<br>' . $context->getTranslator()->trans('Comp. brand') . ': ' . $objectProduct->brand;
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
    if (!is_numeric($category_id) || $category_id <= 0) {
        throw new Exception('Invalid category ID');
    }

    $name = getModuleInfo('name');
    $login = Configuration::get($name . '_login');
    $password = Configuration::get($name . '_password');

    $file = getModuleInfo('e_ecommerce_url') . '/api/utils/csvdata/prodstock?v=2&l=' . urlencode($login) . '&p=' . urlencode($password) . '&idcat=' . urlencode((int)$category_id);

    if (!filter_var($file, FILTER_VALIDATE_URL)) {
        throw new Exception('Invalid URL format');
    }

    $fileData = fopen($file, 'r');
    if ($fileData === false) {
        throw new Exception('Unable to open remote file');
    }

    $line = fgetcsv($fileData, 0, ';');
    $header = [];
    foreach ($line as $val) {
        $header[] = trim($val);
    }

    $cats_array = explode(',', Configuration::get($name . '_' . $category_id . '_categories'));
    $i = 0;
    $result = [];
    while (($line = fgetcsv($fileData, 0, ';')) !== false) {
        if (++$i === 1) {
            continue;
        }
        $new_entry = array_combine($header, array_map('trim', $line));
        if (in_array($new_entry['level_3'], $cats_array)) {
            $result[] = $new_entry;
        }
    }

    fclose($fileData);
    return $result;
}

function runCron3($macro_cat)
{
    $module_name = getModuleInfo('name');
    $country_l = Tools::strtolower(Configuration::get($module_name . '_country'));

    $result_html = '';

    p('Section: ' . $macro_cat . '<br />');

    if (Tools::strlen($macro_cat) > 0) {
        $offset = 0;
        $products = getCatStock($macro_cat);
        while (!empty($products) && $offset < 1) {
            p('CATEGORY ' . $macro_cat . ': IMPORT offset ' . $offset . '<br />');
            foreach ($products as $product) {
                p('Set quantity product ' . $product['id'] . ' ' . $product['code'] . ' ' . $product['version_data']);
                $accessroyImport = new AccessoryImporter();
                $accessroyImport->saveQuantity($product['id'], $product['stock']);
                if ($accessroyImport->getVersion($product['id']) >= $product['version_data']) {
                    p('Skip product ' . $product['id'] . ' latest version already');
                }
                else {
                    p('Importing product ' . $product['id']);

                    $all_product_data = getSingleProduct($product['id']);
                    $objectProduct = json_decode(json_encode($all_product_data), false);
                    $objectProduct->reference = $objectProduct->code_simple;
                    $objectProduct->name = $objectProduct->title->{$country_l};
                    $objectProduct->meta_keywords = $objectProduct->keywords;
                    $objectProduct->price = $objectProduct->price->price;
                    $objectProduct->street_price = $objectProduct->price_a;
                    $objectProduct->description = strip_unsafe($objectProduct->descr->{$country_l}, $img = false);
                    $objectProduct->quantity = $objectProduct->stock;
                    $objectProduct->url_image = json_decode(json_encode($objectProduct->photos), true)[0];
                    $objectProduct->local_category = $objectProduct->level_3;
                    $objectProduct->meta_description = '';
                    $objectProduct->meta_title = $objectProduct->name;
                    $objectProduct->short_description = 'Sizes: ' . $objectProduct->dimensions . '<br>Box: ' . $objectProduct->qty_box . '<br>Color: ' . $objectProduct->color . '<br>Certificate: ' . $objectProduct->certificate . '<br>Comp. brand: ' . $objectProduct->brand;
                    $objectProduct->version = $objectProduct->last_update;
                    $objectProduct->id_manufactuter = $accessroyImport->getManufacturerId($objectProduct->brand);
                    $objectProduct->manufactuter = $objectProduct->brand;
                    $objectProduct->ean13 = $objectProduct->barcode;

                    $accessroyImport->setProductSource($objectProduct);
                    $accessroyImport->save();
                }
            }
            $offset++;
        }

        p('Starting periodic cleaning of obsolete products.');
        $result_html .= setProductsDisabled2($macro_cat);
    }

    return $result_html;
}

function getRootCategories()
{
    $available_cats = availableCategories();
    $root_cats = [];

    if (is_array($available_cats)) {
        $cat1 = 0;
        foreach ($available_cats as $cat) {
            if ($cat1 != $cat['Cat1']) {
                $new_cat = ['Cat1' => $cat['Cat1'], 'description1' => $cat['description1']];

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
    $country_l = Tools::strtolower(Configuration::get($module_name . '_country'));

    $result_html = '';

    $root_cats = getRootCategories();
    foreach ($root_cats as $root_cat)
        $result_html = runCron3($root_cat);

    return $result_html;
}

function dropship()
{
    $module_name = getModuleInfo('name');
    $debug = (bool)Configuration::get($module_name . '_debug_mode');

    $id_order = (int)Tools::getValue('id_o');
    $cart = new Order((int)$id_order);
    $address = new Address($cart->id_address_delivery);

    $dropship_address = [];
    $dropship_products = [];

    $dropship_address['destination_firstname'] = $address->firstname;
    $dropship_address['destination_lastname'] = $address->lastname;
    $dropship_address['destination_company'] = $address->company;
    $dropship_address['destination_address'] = $address->address1 . ' ' . $address->address2;
    $dropship_address['destination_postcode'] = $address->postcode;
    $dropship_address['destination_country'] = $address->country;
    $dropship_address['destination_city'] = $address->city;
    $dropship_address['destination_region'] = State::getNameById($address->id_state);

    $destination_phone = $address->phone . ' ' . $address->phone_mobile;

    $dropship_address['destination_phone'] = $destination_phone;

    if (_PS_VERSION_ >= '1.7.7.0') {
        $products = $cart->getProductsDetail();
    } else {
        $products = $cart->getProducts();
    }

    foreach ($products as $product) {
        addProductToCart($product['supplier_reference'], $product['product_quantity']);
        $new_drop_product = ['code' => $product['supplier_reference'], 'qty' => $product['product_quantity']];

        array_push($dropship_products, $new_drop_product);
    }

    if ($debug) {
        p($dropship_address);
    }
    setShippingAddress($dropship_address);

    $login = Configuration::get($module_name . '_login');
    $password = Configuration::get($module_name . '_password');
    $new_url = getModuleInfo('e_ecommerce_url') . '/checkout?l=$login&p=$password';
    Tools::redirect($new_url);

    return true;
}

function getActiveCart()
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();
    
    $debug = (bool)Configuration::get($module_name . '_debug_mode');
    
    $api_url_new = getModuleInfo('api_url_new');

    $con = curl_init();
    $url = $api_url_new . '/api/order/cart?jwt=' . $jwt;
    
    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);    

    $res_curl = curl_exec($con);
    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($retcode != 200) {
        $info = curl_getinfo($con);
        p($info);
        p($res_curl);
    }

    curl_close($con);

    $res = json_decode($res_curl, true);

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

function getNewCart()
{
    $jwt = getAccessJWT();
    $api_url_new = getModuleInfo('api_url_new');
    $url = $api_url_new . '/api/order/cart?jwt=' . $jwt;

    $con = curl_init();

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-from-urlencoded']);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($con, CURLOPT_POSTFIELDS, 1);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    curl_close($con);

    $res = json_decode($res_curl, true);

    return $res['id'];
}

function addProductToCart($code, $qty)
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();
    $cartId = getActiveCart();

    $debug = (bool)Configuration::get($module_name . '_debug_mode');

    $api_url_new = getModuleInfo('api_url_new');

    $con = curl_init();
    $url = $api_url_new . '/api/order/cart/' . $cartId . '?jwt=' . $jwt;

    $data = '{"type": "PUT_PRODUCT", "value": {"code": "' . $code . '", "qta": ' . $qty . '}}';

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($con, CURLOPT_POSTFIELDS, $data);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);

    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($debug) {
        if ($retcode != 200) {
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
    $regionNumber = regionStringToNumber($dropship_address['destination_region'], $countryNumber);
    $jwt = getAccessJWT();
    $cartId = getActiveCart();
    $api_url_new = getModuleInfo('api_url_new');

    $con = curl_init();
    $url = $api_url_new . '/api/order/cart/' . $cartId . '?jwt=' . $jwt;

    $debug = (bool)Configuration::get($module_name . '_debug_mode');

    $data =
        '{"type": "PUT_ADDR",
            "value": {"spedizione": {
                "nome": "' . $dropship_address['destination_firstname'] . ' ' . $dropship_address['destination_lastname'] . '",
                "ragione_sociale": "' . $dropship_address['destination_company'] . '",
                "nazione": ' . $countryNumber . ',
                "provincia": ' . $regionNumber . ',
                "citta": "' . $dropship_address['destination_city'] . '",
                "indirizzo": "' . $dropship_address['destination_address'] . '",
                "cap": "' . $dropship_address['destination_postcode'] . '",
                "telefono": "' . $dropship_address['destination_phone'] . '"
                }
            }
        }';

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($con, CURLOPT_POSTFIELDS, $data);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($debug) {
        if ($retcode != 200) {
            $info = curl_getinfo($con);
            p($info);
            p($res_curl);
        }
    }

    curl_close($con);

    // fare una nuova put per mettere il FLAG DROPSHIP a true
}

function countryStringToNumber($countryString)
{
    $module_name = getModuleInfo('name');

    $api_url_new = getModuleInfo('api_url_new');
    $url = $api_url_new . '/api/utils/getCountryList';

    $debug = (bool)Configuration::get($module_name . '_debug_mode');

    $con = curl_init();

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($retcode != 200) {
        $info = curl_getinfo($con);
        p($info);
        p($res_curl);
    }

    curl_close($con);

    $res = json_decode($res_curl, true);

    foreach ($res as $countries) {
        if ($countries['name'] == $countryString) {
            return $countries['id'];
        }
    }

    foreach ($res as $countries) {
        if ($countries['name'] == Configuration::get($module_name . '_country')) {
            return $countries['id'];
        }
    }

    return getModuleInfo('default_country_id');
}

function regionStringToNumber($regionString, $countryNumber)
{
    $module_name = getModuleInfo('name');

    $api_url_new = getModuleInfo('api_url_new');
    $url = $api_url_new . '/api/utils/getCityList/' . $countryNumber;

    $debug = (bool)Configuration::get($module_name . '_debug_mode');

    $con = curl_init();

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

    $res_curl = curl_exec($con);
    if ($debug) {
        p($res_curl);
    }

    $retcode = curl_getinfo($con, CURLINFO_HTTP_CODE);
    if ($retcode != 200) {
        $info = curl_getinfo($con);
        p($info);
        p($res_curl);
    }

    curl_close($con);

    $res = json_decode($res_curl, true);

    foreach ($res as $regions) {
        if ($regions['name'] == $regionString) {
            return $regions['id'];
        }
    }

    return getModuleInfo('default_region_id');
}

function strip_unsafe($string, $img = false)
{
    // Unsafe HTML tags that members may abuse
    $unsafe = [
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
        '/<\/html>/is'
    ];

    // Remove graphic too if the user wants
    if ($img == true) {
        $unsafe[] = '/<img(.*?)>/is';
    }

    $string = preg_replace($unsafe, '', $string);

    return $string;
}
