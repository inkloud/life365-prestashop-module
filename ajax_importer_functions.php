<?php
/**
 * 2007-2026 PrestaShop
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
 * @copyright 2007-2026 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/AccessoryImporter.php';

if (!function_exists('p')) {
    function p($msg)
    {
        if (is_array($msg) || is_object($msg)) {
            $msg = '<pre>' . print_r($msg, true) . '</pre>';
        }

        echo $msg;

        // L'importer gira per minuti: mandiamo il log riga per riga invece di
        // tenerlo in buffer fino alla fine, così si vede dove si ferma.
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }
}

function getModuleInfo($info)
{
    $module_name = 'life365';
    // La versione la prende dal modulo, così non va riallineata a mano a ogni release
    static $module_version = null;
    if ($module_version === null) {
        $module = Module::getInstanceByName($module_name);
        $module_version = ($module && !empty($module->version)) ? $module->version : 'unknown';
    }
    $user_app = 'PrestaShop module ver: ' . $module_version;
    // Un solo portale per tutti i paesi: la nazione sceglie il magazzino
    $b2b_url = 'https://b2b.life365.eu';
    $country_default = [
        'IT' => 102,
        'PT' => 159,
        'ES' => 188,
        'NL' => 150,
    ];
    $region_default = [
        'IT' => 1,
        'PT' => 125,
        'ES' => 114,
        'NL' => 130,
    ];
    // Ogni nazione lavora sul magazzino del proprio paese
    $warehouse_default = [
        'IT' => 1,
        'ES' => 17,
        'NL' => 18,
        'PT' => 21,
    ];
    $country_id = Configuration::get($module_name . '_country') ?: 'IT';
    $detail = '';

    switch ($info) {
        case 'api_url_new':
        case 'e_ecommerce_url':
            $detail = $b2b_url;
            break;

        case 'name':
            $detail = $module_name;
            break;

        case 'user_app':
            $detail = $user_app;
            break;

        case 'default_country_id':
            $detail = $country_default[$country_id];
            break;

        case 'default_region_id':
            $detail = $region_default[$country_id];
            break;

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

        case 'warehouse_id':
            $detail = isset($warehouse_default[$country_id]) ? $warehouse_default[$country_id] : 0;
            break;
    }

    return $detail;
}

function getTranslator()
{
    if (class_exists('\PrestaShop\PrestaShop\Adapter\SymfonyContainer')) {
        try {
            $container = PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
            if ($container) {
                // PrestaShop 8.x: usa il servizio 'translator'
                if (version_compare(_PS_VERSION_, '8.0.0', '>=')) {
                    return $container->get('translator');
                }
                // PrestaShop 1.7.x: usa il legacy context
                if ($container->has('prestashop.adapter.legacy.context')) {
                    $legacyContext = $container->get('prestashop.adapter.legacy.context');

                    return $legacyContext->getTranslator();
                }
            }
        } catch (Exception $e) {
            // Fallthrough to alternative methods
        }
    }

    if (class_exists('Translate')) {
        return new class {
            public function trans($string)
            {
                return Translate::getModuleTranslation('life365', $string, 'life365');
            }
        };
    }

    return new class {
        public function trans($string)
        {
            return $string;
        }
    };
}

function getAccessJWT()
{
    $_api_url_new = getModuleInfo('api_url_new');
    $module_name = getModuleInfo('name');
    $user_app = getModuleInfo('user_app');

    $login = Configuration::get($module_name . '_login');
    $password = Configuration::get($module_name . '_password');
    $referer = $_SERVER['HTTP_HOST'] ?? '';

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

/**
 * Senza JWT le API rispondono 200 ma senza prezzo cliente: meglio fermarsi
 * subito con un messaggio chiaro che importare prodotti a prezzo mancante.
 *
 * @return bool
 */
function apiLoginOk()
{
    return Tools::strlen(getAccessJWT()) > 1;
}

function getAccessToken()
{
    // Questa funzione non è più usata - manteniamo per retrocompatibilità
    // ma ritorniamo sempre un nuovo JWT
    return getAccessJWT();
}

/**
 * Chiamata GET alle API, con il codice HTTP restituito nel secondo parametro.
 *
 * @param string $url
 * @param int $http_code
 *
 * @return array|null
 */
/**
 * Scarica una risorsa dalle API e restituisce il corpo grezzo.
 *
 * @param string $url
 * @param int $http_code
 *
 * @return string|null null se curl manca o la richiesta fallisce
 */
function apiGetRaw($url, &$http_code)
{
    $http_code = 0;

    if (!function_exists('curl_init')) {
        return null;
    }

    $con = curl_init();

    curl_setopt($con, CURLOPT_URL, $url);
    curl_setopt($con, CURLOPT_HEADER, false);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($con, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($con, CURLOPT_TIMEOUT, 600);

    $res_curl = curl_exec($con);
    $http_code = (int) curl_getinfo($con, CURLINFO_HTTP_CODE);
    curl_close($con);

    return $res_curl === false ? null : $res_curl;
}

function apiGetJson($url, &$http_code)
{
    $res_curl = apiGetRaw($url, $http_code);

    if ($res_curl === null) {
        return null;
    }

    $res = json_decode($res_curl, true);

    return is_array($res) ? $res : null;
}

/**
 * Estrae il valore nella lingua dello shop da un campo che le nuove API
 * restituiscono come dizionario per lingua e le vecchie come stringa.
 *
 * @param mixed $value
 * @param string $lang
 *
 * @return string
 */
function apiLangValue($value, $lang)
{
    if (is_object($value)) {
        $value = json_decode(json_encode($value), true);
    }

    if (is_array($value)) {
        if (isset($value[$lang]) && $value[$lang] !== '') {
            return (string) $value[$lang];
        }
        if (isset($value['en']) && $value['en'] !== '') {
            return (string) $value['en'];
        }
        $value = array_filter($value, 'is_scalar');

        return empty($value) ? '' : (string) reset($value);
    }

    return (string) $value;
}

/**
 * Sceglie la riga di product_stocks da usare per un prodotto.
 *
 * Lo shop lavora su un solo magazzino, quello della nazione configurata:
 * (product_id, warehouse_id) è univoca e identifica il product_stock_id.
 * Senza riga per quel magazzino il prodotto non è vendibile dallo shop.
 *
 * @param array $data
 * @param int $warehouse_id
 *
 * @return array
 */
function pickStockRow($data, $warehouse_id = 0)
{
    if (empty($data['product_stocks']) || !is_array($data['product_stocks'])) {
        return [];
    }

    foreach ($data['product_stocks'] as $row) {
        if (!is_array($row) || !isset($row['warehouse_id'])) {
            continue;
        }
        if ((int) $row['warehouse_id'] === (int) $warehouse_id) {
            return $row;
        }
    }

    return [];
}

/**
 * Arrotondamento prudenziale dello stock pubblicato, stesso scaglionamento
 * applicato dalle API nel CSV prodstock.
 *
 * @param int $stock
 * @param int $min_qty_delivery
 *
 * @return int
 */
function warehouseStockRound($stock, $min_qty_delivery = 1)
{
    $stock = (int) $stock;
    $min_qty_delivery = max(1, (int) $min_qty_delivery);

    if ($stock <= 1) {
        return $stock;
    }

    $mapping = [
        [1, 3, 1.0],
        [3, 7, 0.8],
        [7, 15, 0.7],
        [15, 50, 0.6],
        [50, 200, 0.5],
        [200, 600, 0.4],
        [600, 1800, 0.3],
        [1800, 4000, 0.2],
    ];

    $ratio = 0.1;
    $floor = 4000;
    foreach ($mapping as $range) {
        if ($stock >= $range[0] && $stock < $range[1]) {
            $ratio = $range[2];
            $floor = $range[0];
            break;
        }
    }

    // Half-even come l'arrotondamento applicato dalle API
    $rounded = $ratio == 1.0 ? 1 : (int) round($ratio * $stock, 0, PHP_ROUND_HALF_EVEN);
    $rounded = max($rounded, $floor);
    $rounded = max($rounded, $min_qty_delivery);

    return min($rounded, $stock);
}

/**
 * Converte il payload API nel formato atteso da AccessoryImporter.
 * Accetta sia il vecchio payload (API it2) sia quello unificato b2b.
 *
 * Ritorna null se manca il prezzo cliente: in quel caso il prodotto va saltato,
 * altrimenti verrebbe importato a prezzo zero.
 *
 * @param array $data
 * @param int $external_id id già presente in life365_product, se noto
 *
 * @return object|null
 */
function normalizeApiProduct($data, $external_id = 0, &$reason = '')
{
    $module_name = getModuleInfo('name');
    $country_l = Tools::strtolower(Configuration::get($module_name . '_country') ?: 'IT');
    $warehouse_id = (int) getModuleInfo('warehouse_id');
    $translator = getTranslator();
    $reason = '';

    if (!is_array($data) || empty($data['id'])) {
        $reason = 'empty payload';

        return null;
    }

    $stock = pickStockRow($data, $warehouse_id);
    $product = json_decode(json_encode($data), false);

    $product_id = (int) $data['id'];

    if (empty($stock)) {
        // Vecchio payload (API it2): nessuna riga di stock, i dati sono piatti
        if (isset($data['product_stocks'])) {
            $reason = 'not stocked in warehouse ' . $warehouse_id;

            return null;
        }
        $stock = [];
    }

    $product_stock_id = 0;
    if (isset($stock['product_stock_id'])) {
        $product_stock_id = (int) $stock['product_stock_id'];
    } elseif (isset($data['product_stock_id'])) {
        $product_stock_id = (int) $data['product_stock_id'];
    }

    // La chiave esterna è il product_stock_id del magazzino della nazione:
    // (product_id, warehouse_id) lo identifica in modo univoco ed è lo stesso
    // id che il CSV prodstock ha sempre restituito.
    $product->product_id = $product_id;
    $product->product_stock_id = $product_stock_id;
    if ($product_stock_id > 0) {
        $product->id = $product_stock_id;
    } elseif ($external_id > 0) {
        $product->id = (int) $external_id;
    } else {
        $product->id = $product_id;
    }
    $product->warehouse_id = isset($stock['warehouse_id']) ? (int) $stock['warehouse_id'] : $warehouse_id;

    // Chiavi con cui lo shop potrebbe avere mappato questo prodotto in passato:
    // l'id prodotto o la riga di stock di un altro magazzino.
    $legacy_ids = [$product_id];
    if ($external_id > 0) {
        $legacy_ids[] = (int) $external_id;
    }
    if (!empty($data['product_stocks']) && is_array($data['product_stocks'])) {
        foreach ($data['product_stocks'] as $row) {
            if (isset($row['product_stock_id'])) {
                $legacy_ids[] = (int) $row['product_stock_id'];
            }
        }
    }
    $product->legacy_ids = array_values(array_diff(array_unique($legacy_ids), [(int) $product->id]));

    // Prezzo cliente: nuove API dentro la riga di stock, vecchie a livello prodotto
    $price = null;
    if (isset($stock['price']['price']) && is_numeric($stock['price']['price'])) {
        $price = $stock['price']['price'];
    } elseif (isset($data['price']['price']) && is_numeric($data['price']['price'])) {
        $price = $data['price']['price'];
    } elseif (isset($data['price']) && is_numeric($data['price'])) {
        $price = $data['price'];
    }

    if ($price === null) {
        $reason = 'price not available from API';

        return null;
    }

    $street_price = null;
    if (isset($stock['price_a']) && is_numeric($stock['price_a'])) {
        $street_price = $stock['price_a'];
    } elseif (isset($data['price_a']) && is_numeric($data['price_a'])) {
        $street_price = $data['price_a'];
    }

    $product->price = (float) $price;
    $product->street_price = $street_price === null ? (float) $price : (float) $street_price;

    $product->reference = isset($data['code_simple']) ? $data['code_simple'] : (isset($data['isin']) ? $data['isin'] : '');
    $product->name = strip_tags(apiLangValue(isset($data['title']) ? $data['title'] : (isset($data['titles']) ? $data['titles'] : ''), $country_l));
    $product->meta_keywords = apiLangValue(isset($data['keywords']) ? $data['keywords'] : '', $country_l);

    $descr = isset($data['descr']) ? $data['descr'] : (isset($data['descriptions']) ? $data['descriptions'] : '');
    $cleanDescription = strip_unsafe(apiLangValue($descr, $country_l), $img = false);
    // Compress HTML: remove extra whitespace, tabs, and newlines
    $cleanDescription = preg_replace('/\s+/', ' ', $cleanDescription);
    $cleanDescription = preg_replace('/>\s+</', '><', $cleanDescription);
    $cleanDescription = trim($cleanDescription);
    // Decode HTML entities and validate with PrestaShop
    $cleanDescription = html_entity_decode($cleanDescription, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $cleanDescription = Tools::purifyHTML($cleanDescription);
    $product->description = !empty($cleanDescription) ? $cleanDescription : '';

    if (isset($stock['qty_delivery'])) {
        $product->qty_delivery = (int) $stock['qty_delivery'];
    } elseif (empty($data['qty_delivery'])) {
        $product->qty_delivery = 1;
    }

    if (isset($stock['stock'])) {
        // Lo stock della riga API è quello reale: applichiamo lo stesso
        // arrotondamento prudenziale che il CSV prodstock applica a monte.
        $product->stock = warehouseStockRound((int) $stock['stock'], (int) $product->qty_delivery);
    }
    $product->quantity = isset($product->stock) ? (int) $product->stock : 0;

    if (!isset($data['weight']) && isset($data['weight_gr'])) {
        $product->weight = (float) $data['weight_gr'];
    }

    if (!isset($data['barcode'])) {
        $barcodes = isset($data['barcodes']) ? (array) $data['barcodes'] : [];
        $product->barcode = empty($barcodes) ? '' : (string) reset($barcodes);
    }

    if (!isset($data['dimensions'])) {
        $dims = [];
        foreach (['dim_length_mm', 'dim_width_mm', 'dim_height_mm'] as $dim) {
            if (!empty($data[$dim])) {
                $dims[] = (int) $data[$dim];
            }
        }
        $product->dimensions = empty($dims) ? '' : implode('x', $dims) . ' mm';
    }

    if (isset($data['brand']) && is_array($data['brand'])) {
        $product->brand = isset($data['brand']['brand_name']) ? $data['brand']['brand_name'] : '';
    }

    $photos = isset($data['photos']) ? (array) $data['photos'] : [];
    $product->url_image = empty($photos) ? '' : (string) reset($photos);

    $product->local_category = isset($data['level_3']) ? (int) $data['level_3'] : 0;
    $product->meta_description = '';
    $product->meta_title = $product->name;
    $product->short_description = $translator->trans('Sizes') . ': ' . $product->dimensions
        . '<br>' . $translator->trans('Box') . ': ' . (isset($data['qty_box']) ? $data['qty_box'] : '')
        . '<br>' . $translator->trans('Color') . ': ' . (isset($data['color']) ? $data['color'] : '')
        . '<br>' . $translator->trans('Certificate') . ': ' . (isset($data['certificate']) ? $data['certificate'] : '')
        . '<br>' . $translator->trans('Comp. brand') . ': ' . $product->brand;
    // Versione: quella della riga di stock del magazzino, che cambia anche
    // quando la modifica riguarda solo prezzo o giacenza. Il last_update di
    // prodotto resta come riserva per i payload che non la espongono.
    $product->version = isset($data['last_update']) ? (int) $data['last_update'] : 0;
    if (isset($stock['last_update']) && (int) $stock['last_update'] > $product->version) {
        $product->version = (int) $stock['last_update'];
    }
    $product->manufacturer = $product->brand;

    return $product;
}

function getProducts2($category_id)
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();

    $debug = (bool) Configuration::get($module_name . '_debug_mode');

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

/**
 * Scarica un prodotto a partire dall'id salvato in life365_product.
 *
 * Quell'id è storicamente un product_stock_id (è l'id del CSV prodstock), che
 * sulle nuove API b2b si risolve con /api/products/product_stocks/{id}.
 * Se la ricerca fallisce si ritenta con /api/products/{id}, per le
 * installazioni che avessero salvato un id prodotto.
 *
 * @param int $product_id
 *
 * @return array|null
 */
function getSingleProduct($product_id)
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();

    $debug = (bool) Configuration::get($module_name . '_debug_mode');

    $api_url_new = getModuleInfo('api_url_new');

    $paths = [
        '/api/products/product_stocks/' . (int) $product_id,
        '/api/products/' . (int) $product_id,
    ];

    $last_code = 0;
    foreach ($paths as $path) {
        $retcode = 0;
        $res = apiGetJson($api_url_new . $path . '?jwt=' . $jwt, $retcode);

        if ($debug) {
            p($path . ' HTTP ' . $retcode . '<br />');
        }

        if ($retcode == 200 && is_array($res) && !empty($res['id'])) {
            return $res;
        }

        $last_code = $retcode;
    }

    p('API error HTTP ' . $last_code . ' for product ' . $product_id . ': product not found<br />');

    return null;
}

function getProductsDisabled2($category_id)
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();

    $debug = (bool) Configuration::get($module_name . '_debug_mode');

    $api_url_new = getModuleInfo('api_url_new');

    // Il magazzino della nazione: senza di esso l'API segnalerebbe anche i
    // prodotti invisibili in altri magazzini, che qui sono regolarmente in vendita.
    $warehouse_id = (int) getModuleInfo('warehouse_id');

    $con = curl_init();
    $url = $api_url_new . '/api/warehouse/getDisabledProducts/' . $category_id . '?jwt=' . $jwt;
    if ($warehouse_id > 0) {
        $url .= '&warehouse_id=' . $warehouse_id;
    }

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
    $debug = (bool) Configuration::get($module_name . '_debug_mode');

    $result_html = '';

    if ($category_id > 0) {
        $products = getProductsDisabled2($category_id);
        if (!empty($products) && is_array($products)) {
            p('MACROCATEGORY ' . $category_id . ' - CLEANING PHASE: ' . count($products) . ' candidates<br />');
            $accessroyImport = new AccessoryImporter();
            $disabled = 0;

            foreach ($products as $product) {
                if (!is_array($product)) {
                    continue;
                }
                if ($debug) {
                    p($product);
                }

                // Le nuove API danno il product_stock_id del magazzino della
                // nazione, che è la chiave di life365_product; i payload più
                // vecchi hanno solo l'id prodotto, da risolvere per reference.
                $product_stock_id = isset($product['product_stock_id']) ? (int) $product['product_stock_id'] : 0;
                $reference = isset($product['reference']) ? $product['reference'] : '';
                $external_id = 0;

                if ($product_stock_id > 0 && $accessroyImport->ifExistId($product_stock_id) != 0) {
                    $external_id = $product_stock_id;
                } else {
                    $product_id = isset($product['id']) ? (int) $product['id'] : 0;
                    $external_id = $accessroyImport->resolveExternalIdByProductId($product_id, $reference);
                }

                if (!$external_id) {
                    continue;
                }

                $objectProduct = json_decode(json_encode($product), false);
                $objectProduct->id = (int) $external_id;

                try {
                    $accessroyImport->setProductSource($objectProduct);
                    if ($accessroyImport->disable()) {
                        ++$disabled;
                        p('Disabled product ' . $external_id . ' ' . $reference . '<br />');
                    }
                } catch (Throwable $e) {
                    // Un prodotto rotto non deve far saltare tutta la pulizia.
                    p('Disable failed for product ' . $external_id . ' ' . $reference . ': ' .
                        htmlspecialchars($e->getMessage()) . '<br />');
                }
            }

            p('Cleaning done: ' . $disabled . ' products disabled<br />');
        }
    }

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
        }

        return false;
    }

    return false;
}

function checkLogon()
{
    $jwt = getAccessJWT();

    if (Tools::strlen($jwt) > 1) {
        return '<span style="color:green;font-weight:bold;">&#10004; Ok</span>';
    }

    return '<span style="color:red;font-weight:bold;">&#10008; Error</span>';
}

function getProds($opt_cat = 0)
{
    $module_name = getModuleInfo('name');

    $debug = (bool) Configuration::get($module_name . '_debug_mode');
    $offset = Tools::getValue('offset');
    $qty = Tools::getValue('qty');
    $macro_cat = 0;

    if ($opt_cat == 0) {
        $cat = Tools::getValue('cat');
    } else {
        $cat = $opt_cat;
    }

    if ($offset > 0) {
        return '';
    }

    $result_html = '<pre>';

    if (!apiLoginOk()) {
        return $result_html . 'Login failed: check Life365 login and password in the module settings</pre>';
    }

    if (array_filter($products = getProducts2($cat))) {
        $result_html .= 'CATEGORY ' . $cat . ': IMPORT offset ' . $offset . '<br />';
        $serviceAccessoryImport = new AccessoryImporter();
        foreach ($products as $product) {
            if ($debug) {
                p($product);
            }
            $macro_cat = isset($product['level_1']) ? $product['level_1'] : 0;

            if (!isset($product['level_3']) || $product['level_3'] != $cat) {
                $result_html .= 'Skip product ' . $product['id'] . ' not native category (' . (isset($product['level_3']) ? $product['level_3'] : '') . ')<br />';
                continue;
            }

            $reason = '';
            $objectProduct = normalizeApiProduct($product, 0, $reason);
            if ($objectProduct === null) {
                $result_html .= 'Skip product ' . $product['id'] . ': ' . $reason . '<br />';
                continue;
            }

            $result_html .= 'Set quantity product ' . $objectProduct->id . ' ' . $objectProduct->reference . ' ' . $objectProduct->version . '<br />';
            $accessroyImport = new AccessoryImporter();
            $accessroyImport->reconcileExternalId((int) $objectProduct->id, $objectProduct->legacy_ids);
            $accessroyImport->saveQuantity($objectProduct->id, $objectProduct->quantity);

            if ($serviceAccessoryImport->getVersion($objectProduct->id) >= $objectProduct->version) {
                $result_html .= 'Skip product ' . $objectProduct->id . ' latest version already <br />';
                continue;
            }
            $result_html .= 'Importing product ' . $objectProduct->id . '<br />';

            $objectProduct->id_manufacturer = $serviceAccessoryImport->getManufacturerId($objectProduct->manufacturer);
            $objectProduct->ean13 = $objectProduct->barcode;

            $accessroyImport->setProductSource($objectProduct);
            $accessroyImport->save();
        }
    }
    $result_html .= '</pre>';

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

    $warehouse_id = (int) getModuleInfo('warehouse_id');

    // Il jwt viaggia insieme a login e password: se il confronto diretto
    // delle credenziali non passa, le API ripiegano sul token già ottenuto.
    $jwt = getAccessJWT();

    $file = getModuleInfo('e_ecommerce_url') . '/api/utils/csvdata/prodstock?v=2&l=' . urlencode($login) . '&p=' . urlencode($password) . '&idcat=' . urlencode((string) $category_id) . '&warehouse_id=' . $warehouse_id . '&jwt=' . urlencode($jwt);

    if (!filter_var($file, FILTER_VALIDATE_URL)) {
        throw new Exception('Invalid URL format');
    }

    // Niente fopen() sull'URL: molti hosting hanno allow_url_fopen disattivato.
    // Scarichiamo con curl, come il resto del modulo, e leggiamo da un buffer.
    $csv = apiGetRaw($file, $http_code);

    if ($csv === null || $csv === '') {
        throw new Exception('Unable to download the product list (HTTP ' . $http_code . ') from ' . getModuleInfo('e_ecommerce_url'));
    }

    if ($http_code !== 200) {
        // Login e password sono case sensitive: stampiamo il login così com'è
        // configurato, da confrontare con quello registrato su b2b.
        throw new Exception('The product list returned HTTP ' . $http_code . ' for login "' . $login . '" (password length ' . Tools::strlen($password) . ', jwt ' . (Tools::strlen($jwt) > 1 ? 'ok' : 'missing') . '): the credentials in the module settings do not match the ones registered on ' . getModuleInfo('e_ecommerce_url'));
    }

    $fileData = fopen('php://temp', 'r+');
    if ($fileData === false) {
        throw new Exception('Unable to open the temporary buffer');
    }

    fwrite($fileData, $csv);
    rewind($fileData);
    unset($csv);

    $line = fgetcsv($fileData, 0, ';');
    if (!is_array($line)) {
        fclose($fileData);

        throw new Exception('The product list is empty or not a CSV');
    }

    $header = [];
    foreach ($line as $val) {
        $header[] = trim($val);
    }

    $cats_array = explode(',', Configuration::get($name . '_' . $category_id . '_categories'));
    $i = 0;
    $result = [];
    while (($line = fgetcsv($fileData, 0, ';')) !== false) {
        $new_entry = array_combine($header, array_map('trim', $line));

        // Se il CSV espone il magazzino teniamo solo quello della nazione;
        // altrimenti il filtro avviene più avanti, sulla risposta delle API.
        if ($warehouse_id > 0 && isset($new_entry['warehouse_id'])
            && (int) $new_entry['warehouse_id'] !== $warehouse_id) {
            continue;
        }

        if (in_array($new_entry['level_3'], $cats_array)) {
            $result[] = $new_entry;
        }
    }

    fclose($fileData);

    return $result;
}

function runCron3($macro_cat)
{
    $result_html = '';

    p('Section: ' . $macro_cat . '<br />');

    if (!apiLoginOk()) {
        p('Login failed: check Life365 login and password in the module settings<br />');

        return $result_html;
    }

    if (Tools::strlen($macro_cat) > 0) {
        $offset = 0;
        $products = getCatStock($macro_cat);
        while (!empty($products) && $offset < 1) {
            p('<h2>CATEGORY ' . $macro_cat . ': IMPORT offset ' . $offset . '</h2>');
            foreach ($products as $product) {
                $accessroyImport = new AccessoryImporter();
                $csv_id = (int) $product['id'];

                if ($accessroyImport->ifExistId($csv_id) != 0 && $accessroyImport->getVersion($csv_id) >= $product['version_data']) {
                    p('Skip product ' . $csv_id . ' latest version already<br />');
                    p('Set quantity product ' . $csv_id . ' ' . $product['code'] . ' ' . $product['version_data'] . '<br />');
                    $accessroyImport->saveQuantity($csv_id, $product['stock']);
                    continue;
                }

                p('Importing product ' . $csv_id . '<br />');

                $all_product_data = getSingleProduct($csv_id);
                if (!is_array($all_product_data) || empty($all_product_data['id'])) {
                    p('Skip product ' . $csv_id . ': not available from API<br />');
                    continue;
                }

                // La riga di stock viene scelta sul magazzino della nazione:
                // l'id del CSV può puntare a un altro magazzino.
                $reason = '';
                $objectProduct = normalizeApiProduct($all_product_data, $csv_id, $reason);
                if ($objectProduct === null) {
                    p('Skip product ' . $csv_id . ': ' . $reason . '<br />');
                    // Il prodotto non è più vendibile da questo shop: azzeriamo
                    // la giacenza senza toccare il prodotto in catalogo.
                    if ($accessroyImport->ifExistId($csv_id) != 0) {
                        $accessroyImport->saveQuantity($csv_id, 0);
                    }
                    continue;
                }

                try {
                    $accessroyImport->reconcileExternalId((int) $objectProduct->id, $objectProduct->legacy_ids);

                    $objectProduct->id_manufacturer = $accessroyImport->getManufacturerId($objectProduct->manufacturer);
                    $objectProduct->ean13 = $objectProduct->barcode;

                    $accessroyImport->setProductSource($objectProduct);
                    $accessroyImport->save();

                    p('Set quantity product ' . $objectProduct->id . ' ' . $objectProduct->reference . ' ' . $objectProduct->version . '<br />');
                    $accessroyImport->saveQuantity($objectProduct->id, $objectProduct->quantity);
                } catch (Throwable $e) {
                    // Un prodotto che non si lascia importare non deve fermare
                    // l'intera categoria: lo segnaliamo e si prosegue.
                    p('Import failed for product ' . $objectProduct->id . ' ' . $objectProduct->reference . ': ' .
                        htmlspecialchars(get_class($e) . ': ' . $e->getMessage()) . '<br />');
                }
            }
            ++$offset;
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

    $module_name = getModuleInfo('name');
    $country_l = Tools::strtolower(Configuration::get($module_name . '_country') ?: 'IT');

    if (is_array($available_cats)) {
        $cat1 = 0;
        foreach ($available_cats as $cat) {
            // Vecchio albero: Cat1/description1. Nuovo albero b2b: id/title.
            $cat_id = isset($cat['Cat1']) ? $cat['Cat1'] : (isset($cat['id']) ? $cat['id'] : 0);
            $cat_name = isset($cat['description1']) ? $cat['description1'] : (isset($cat['title']) ? $cat['title'] : '');

            if ($cat_id && $cat1 != $cat_id) {
                $new_cat = ['Cat1' => $cat_id, 'description1' => apiLangValue($cat_name, $country_l)];

                array_push($root_cats, $new_cat);
                $cat1 = $cat_id;
            }
        }
    }

    return $root_cats;
}

function runCron()
{
    $result_html = '';

    $root_cats = getRootCategories();
    foreach ($root_cats as $root_cat) {
        $result_html = runCron3($root_cat['Cat1']);
    }

    return $result_html;
}

function dropship()
{
    $module_name = getModuleInfo('name');
    $debug = (bool) Configuration::get($module_name . '_debug_mode');

    $id_order = (int) Tools::getValue('id_o');
    $cart = new Order((int) $id_order);
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
    $new_url = getModuleInfo('e_ecommerce_url') . '?jwt=' . getAccessJWT();
    Tools::redirect($new_url);

    return true;
}

function getActiveCart()
{
    $module_name = getModuleInfo('name');
    $jwt = getAccessJWT();

    $debug = (bool) Configuration::get($module_name . '_debug_mode');

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

    $debug = (bool) Configuration::get($module_name . '_debug_mode');

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

    $debug = (bool) Configuration::get($module_name . '_debug_mode');

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

    $debug = (bool) Configuration::get($module_name . '_debug_mode');

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
        if ($countries['name'] == (Configuration::get($module_name . '_country') ?: 'IT')) {
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

    $debug = (bool) Configuration::get($module_name . '_debug_mode');

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
        '/<\/html>/is',
    ];

    // Remove graphic too if the user wants
    if ($img == true) {
        $unsafe[] = '/<img(.*?)>/is';
    }

    $string = preg_replace($unsafe, '', $string);

    return $string;
}
