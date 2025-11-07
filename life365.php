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
 * @author    Giancarlo Spadini
 * @copyright 2007-2025 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once 'AccessoryImporter.php';

class Life365 extends Module
{
    private $c_html = '';

    private $c_api_url_new = [
        'IT' => 'https://it2.life365.eu',
        'PT' => 'https://pt2.life365.eu',
        'ES' => 'https://es2.life365.eu',
        'NL' => 'https://nl2.life365.eu',
    ];

    public function __construct()
    {
        $this->name = 'life365';
        $this->tab = 'quick_bulk_update';
        $this->version = '8.1.106';
        $this->author = 'Giancarlo Spadini';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = ['min' => '1.7.4', 'max' => '8.2.1'];
        $this->module_key = '17fe516516b4f12fb1d877a3600dbedc';

        parent::__construct();

        $this->displayName = $this->l('Life365/Inkloud dropshipping');
        $this->description = $this->l('Expand your shop. Start now selling over 20.000 products in dropshipping!');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function getName()
    {
        return $this->name;
    }

    public function install()
    {
        if (parent::install() == false || !$this->installDB() || !$this->registerHooks()) {
            return false;
        }

        Configuration::updateValue($this->name . '_import_current_category', 0);
        Configuration::updateValue('PS_ALLOW_HTML_IFRAME', 1);

        return true;
    }

    public function enable($forceAll = false)
    {
        if (!parent::enable($forceAll = false)) {
            return false;
        }

        Configuration::updateValue('PS_ALLOW_HTML_IFRAME', 1);

        return true;
    }

    public function installDB()
    {
        $t_sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . $this->name . '_product` (
                  `id_product_external` int(10) unsigned NOT NULL,
                  `date_import` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  `id_product_ps` int(10) unsigned NOT NULL,
                  `version` int(10) unsigned NOT NULL DEFAULT \'0\',
                  PRIMARY KEY (`id_product_external`),
                  UNIQUE KEY `id_product_ps_unique` (`id_product_ps`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8;';
        Db::getInstance()->execute($t_sql);
        $t_sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . $this->name . '_category` (
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
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $this->name . '_product`');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . $this->name . '_category`');

        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall()
            || !$this->uninstallDB()
            || !Configuration::deleteByName('LIFE365_NAME')
            || !$this->unregisterHook('displayBackOfficeHeader')
        ) {
            return false;
        }

        return true;
    }

    private function registerHooks()
    {
        return
            $this->registerHook('backOfficeHome')
            && $this->registerHook('DisplayBackOfficeHeader')
            && $this->registerHook('displayAdminOrderTabOrder')
            && $this->registerHook('displayAdminOrderRight')
            && $this->registerHook('displayAdminOrderSide');
    }

    public function hookDisplayAdminOrderTabOrder($params)
    {
        if (_PS_VERSION_ >= '1.7.7.0') {
            $order_id = $params['id_order'];
        } else {
            $order_id = $params['order']->id;
        }

        $this->smarty->assign([
            'order' => $params['order'],
            'dropship_link' => $this->_path . 'ajax_importer.php',
            'dropship_order' => $order_id,
            'dropship_token' => Tools::getAdminToken($this->name),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/dropship.tpl');
    }

    public function hookdisplayAdminOrderRight($params)
    {
        if (_PS_VERSION_ >= '1.7.7.0') {
            $order_id = $params['id_order'];
        } else {
            $order_id = $params['order']->id;
        }
        $this->smarty->assign([
            'order' => $params['order'],
            'dropship_link' => $this->_path . 'ajax_importer.php',
            'dropship_order' => $order_id,
            'dropship_token' => Tools::getAdminToken($this->name),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/dropship.tpl');
    }

    public function hookDisplayAdminOrderSide($params)
    {
        if (_PS_VERSION_ >= '1.7.7.0') {
            $order_id = $params['id_order'];
        } else {
            $order_id = $params['order']->id;
        }
        $this->smarty->assign([
            'order' => $params['order'],
            'dropship_link' => $this->_path . 'ajax_importer.php',
            'dropship_order' => $order_id,
            'dropship_token' => Tools::getAdminToken($this->name),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/dropship.tpl');
    }

    public function hookbackOfficeHome($params)
    {
        return $this->display(__FILE__, 'views/templates/hook/life365.tpl');
    }

    public function hookDisplayBackOfficeHeader($params)
    {
        $this->context->controller->addCSS($this->_path . 'views/css/life365.css', 'all');
    }

    public function getContent()
    {
        if (Tools::isSubmit($this->name . '_importer')) {
            return $this->startImportAjax();
        }

        if (Tools::isSubmit($this->name . '_action_cat_click')) {
            $managed_cat = Tools::getValue($this->name . '_cat_click');
            return $this->manageCats2($managed_cat);
        }

        if (Tools::isSubmit($this->name . '_manage_cats')) {
            return $this->manageCats();
        }

        if (Tools::isSubmit($this->name . '_action_cats_b')) {
            $cat1 = Tools::getValue($this->name . '_cat1');
            $cats_array = Tools::getValue($this->name . '_categories');
            foreach ($cats_array as $cat) {
                $profit = Tools::getValue('profit_' . $cat) * 100;
                $ps_cat = Tools::getValue('cat_ps_' . $cat);

                Db::getInstance()->delete($this->name . '_category', 'id_category_external = ' . (int) $cat);
                Db::getInstance()->insert($this->name . '_category', [
                    'id_category_external' => (int) $cat,
                    'profit' => (int) $profit / 100,
                    'id_category_ps' => (int) $ps_cat,
                ]);
            }

            if (Tools::getValue($this->name . '_categories')) {
                $sub_cat_list = implode(',', Tools::getValue($this->name . '_categories'));
            } else {
                $sub_cat_list = '';
            }

            Configuration::updateValue(
                $this->name . '_' . $cat1 . '_categories',
                $sub_cat_list
            );

            // Force update of all products
            $t_sql = 'UPDATE `' . _DB_PREFIX_ . 'life365_product` SET `version` = 0;';
            Db::getInstance()->execute($t_sql);
        }

        if (Tools::isSubmit($this->name . '_save_other_settings')) {
            Configuration::updateValue($this->name . '_sync_name', Tools::getValue($this->name . '_sync_name'));
            Configuration::updateValue($this->name . '_sync_short_desc', Tools::getValue($this->name . '_sync_short_desc'));
            Configuration::updateValue($this->name . '_sync_desc', Tools::getValue($this->name . '_sync_desc'));
            Configuration::updateValue($this->name . '_sync_price', Tools::getValue($this->name . '_sync_price'));
            Configuration::updateValue($this->name . '_sync_category', Tools::getValue($this->name . '_sync_category'));
            Configuration::updateValue($this->name . '_debug_mode', Tools::getValue($this->name . '_debug_mode'));
            Configuration::updateValue($this->name . '_sync_slow', Tools::getValue($this->name . '_sync_slow'));
            Configuration::updateValue($this->name . '_price_limit', Tools::getValue($this->name . '_price_limit'));
            // Configuration::updateValue($this->name.'_parent_categories', Tools::getValue($this->name.'_parent_categories'));
        }

        if (Tools::isSubmit($this->name . '_submit')) {
            Configuration::updateValue($this->name . '_login', Tools::getValue($this->name . '_login'));
            Configuration::updateValue($this->name . '_password', Tools::getValue($this->name . '_password'));
            Configuration::updateValue($this->name . '_country', Tools::getValue($this->name . '_country'));
            Configuration::updateValue($this->name . '_default_category', Tools::getValue($this->name . '_default_category'));
            Configuration::updateValue($this->name . '_overhead', Tools::getValue($this->name . '_overhead'));
            Configuration::updateValue($this->name . '_default_tax_id', Tools::getValue($this->name . '_default_tax_id'));
        }
        $this->displayForm();
        return $this->c_html;
    }

    private function getCatetoryDepth($id_category)
    {
        $level_depth = Db::getInstance()->getValue(
            'SELECT level_depth
            FROM ' . _DB_PREFIX_ . 'category
            WHERE id_category = ' . (int) $id_category
        );

        return $level_depth;
    }

    private function displayTaxRules($id_selected = 0)
    {
        $result_html = '';

        $result_html .= '<option value="0"' . (($id_selected == 0) ? ' selected="selected"' : '') . '>No Tax</option>';

        $result = TaxRulesGroup::getTaxRulesGroups(true);
        foreach ($result as $tax) {
            $result_html .= '<option value="' . $tax['id_tax_rules_group'] . '"' . (($id_selected == $tax['id_tax_rules_group']) ? ' selected="selected"' : '') . '>';
            $result_html .= $tax['name'];
            $result_html .= '</option>';
        }

        return $result_html;
    }

    private function siteURL()
    {
        if (isset($_SERVER['HTTPS'])
            && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)
            || isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }

        $domainName = $_SERVER['HTTP_HOST'];

        return $protocol . $domainName;
    }

    private function displayCatetoriesChildren($id_parent, $id_selected = 1, $id_lang = 1)
    {
        $result_html = '';
        $result = Category::getChildren($id_parent, $id_lang, true);
        foreach ($result as $cat) {
            $level_depth = $this->getCatetoryDepth($cat['id_category']);
            $result_html .= '<option value="' . $cat['id_category'] . '"' . (($id_selected == $cat['id_category']) ? ' selected="selected"' : '') . '>';
            $result_html .= str_repeat('&nbsp;', $level_depth * 2) . $cat['name'];
            $result_html .= '</option>';
            $result_html .= $this->displayCatetoriesChildren($cat['id_category'], $id_selected, $id_lang);
        }
        return $result_html;
    }

    private function displayExternalCatetories($root_category = 0)
    {
        $selected_categories_array = explode(',', Configuration::get($this->name . '_' . $root_category . '_categories'));
        $available_cats = $this->availableCategories();
        $list_cat3 = '';
        $categories = [];
        $remote_tree_category = [];
        $root_category_name = '';

        if (is_array($available_cats)) {
            foreach ($available_cats as $cat) {
                if ($root_category == 0 || $cat['id'] == $root_category) {
                    $root_category_name = $cat['title'];
                    $remote_tree_category = $cat['zchildren'];
                    $cat1 = $cat['zchildren'];
                    foreach ($cat1 as $cat2) {
                        foreach ($cat2['zchildren'] as $cat3) {
                            $list_cat3 .= $cat3['id'] . ',';
                            $cat_checked = in_array($cat3['id'], $selected_categories_array) ? true : false;

                            $sql = 'SELECT `profit`, `id_category_ps`
                                    FROM `' . _DB_PREFIX_ . $this->name . '_category`
                                    WHERE id_category_external = ' . (int) $cat3['id'];
                            if ($row = Db::getInstance()->getRow($sql)) {
                                $id_cat_ps = $row['id_category_ps'];
                                $profit = $row['profit'];
                            } else {
                                $id_cat_ps = Configuration::get($this->name . '_default_category');
                                $profit = Configuration::get($this->name . '_overhead');
                            }

                            $categories[] = [
                                'cat3' => $cat3['id'],
                                'description3' => $cat3['name'],
                                'checked' => $cat_checked,
                                'id_cat_ps' => $id_cat_ps,
                                'profit' => $profit,
                            ];
                        }
                    }
                }
            }
        }

        $this->context->smarty->assign([
            'module_name' => $this->name,
            'root_category' => $root_category,
            'root_category_name' => $root_category_name,
            'remote_tree_category' => $remote_tree_category,
            'categories' => $categories,
            'list_cat3' => $list_cat3,
            'default_category' => Configuration::get('PS_HOME_CATEGORY'),
            'default_lang' => Configuration::get('PS_LANG_DEFAULT'),
            'all_categories' => $this->displayCatetoriesChildren(Configuration::get('PS_HOME_CATEGORY'), Configuration::get('PS_HOME_CATEGORY'), Configuration::get('PS_LANG_DEFAULT')),
            'l' => function ($string) {
                return $this->l($string);
            },
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/display_external_categories.tpl');
    }

    private function displayForm()
    {
        $myUrl = $this->siteURL();
        $e_commerce_url = [
            'IT' => 'https://www.life365.eu',
            'PT' => 'https://www.life365.pt',
            'ES' => 'https://www.inkloud.es',
            'NL' => 'https://www.inkloud.eu',
        ];
        $country_id = Configuration::get($this->name . '_country');

        $cron_url = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/ajax_importer.php?action=cron&token=' . Tools::getAdminToken($this->name);
        $cron_url2 = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/ajax_importer.php?action=cron3&token=' . Tools::getAdminToken($this->name) . '&mc=';
        $root_cats = $this->getRootCategories();

        $sync_options = [
            [
                'name' => $this->name . '_sync_name',
                'checked' => Configuration::get($this->name . '_sync_name'),
                'label' => $this->l('Product name'),
            ],
            [
                'name' => $this->name . '_sync_short_desc',
                'checked' => Configuration::get($this->name . '_sync_short_desc'),
                'label' => $this->l('Product short description'),
            ],
            [
                'name' => $this->name . '_sync_desc',
                'checked' => Configuration::get($this->name . '_sync_desc'),
                'label' => $this->l('Product description'),
            ],
            [
                'name' => $this->name . '_sync_category',
                'checked' => Configuration::get($this->name . '_sync_category'),
                'label' => $this->l('Reset association with local categories'),
            ],
            [
                'name' => $this->name . '_sync_price',
                'checked' => Configuration::get($this->name . '_sync_price'),
                'label' => $this->l('Product price'),
            ],
        ];

        $check_logon_url = _MODULE_DIR_ . $this->name . '/ajax_importer.php?action=checkLogon&token=' . Tools::getAdminToken($this->name);
        $loader_img_url = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'img/loader.gif';

        $this->context->smarty->assign([
            'module_path' => $this->_path,
            'e_commerce_url' => $e_commerce_url,
            'country_id' => $country_id,
            'cron_url' => $cron_url,
            'cron_url2' => $cron_url2,
            'login' => Configuration::get($this->name . '_login'),
            'password' => Configuration::get($this->name . '_password'),
            'overhead' => Configuration::get($this->name . '_overhead'),
            'default_category' => Configuration::get($this->name . '_default_category'),
            'default_tax_id' => Configuration::get($this->name . '_default_tax_id'),
            'categories' => $this->displayCatetoriesChildren(Configuration::get('PS_HOME_CATEGORY'), Configuration::get($this->name . '_default_category'), Configuration::get('PS_LANG_DEFAULT')),
            'tax_rules' => $this->displayTaxRules(Configuration::get($this->name . '_default_tax_id')),
            'root_cats' => $root_cats,
            'sync_options' => $sync_options,
            'debug_mode' => Configuration::get($this->name . '_debug_mode'),
            'sync_slow' => Configuration::get($this->name . '_sync_slow'),
            'price_limit' => Configuration::get($this->name . '_price_limit'),
            'check_logon_url' => $check_logon_url,
            'loader_img_url' => $loader_img_url,
            'l' => function ($string) {
                return $this->l($string);
            },
        ]);

        $this->c_html .= $this->context->smarty->fetch($this->local_path . 'views/templates/admin/display_form.tpl');
    }

    private function getRootCategories()
    {
        $country_id = Configuration::get($this->name . '_country');
        $available_cats = $this->availableCategories();
        $root_cats = [];

        if (is_array($available_cats)) {
            $cat1 = 0;
            foreach ($available_cats as $cat) {
                if ($cat1 != $cat['id']) {
                    $new_cat = ['Cat1' => $cat['id'], 'description1' => $cat['title']];

                    array_push($root_cats, $new_cat);
                    $cat1 = $cat['id'];
                }
            }
        }

        return $root_cats;
    }

    private function availableCategories()
    {
        $country_id = Configuration::get($this->name . '_country');
        $api_url_new = $this->c_api_url_new[$country_id];

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

    private function manageCats()
    {
        $root_cats = $this->getRootCategories();

        $this->context->smarty->assign([
            'module_path' => $this->_path,
            'root_cats' => $root_cats,
            'request_uri' => $_SERVER['REQUEST_URI'],
            'module_name' => $this->name,
            'l' => function ($string) {
                return $this->l($string);
            },
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/manage_cats.tpl');
    }

    private function manageCats2($managed_cat)
    {
        $root_cats = $this->getRootCategories();
        $cats_html = $this->displayExternalCatetories($managed_cat);

        $this->context->smarty->assign([
            'module_path' => $this->_path,
            'root_cats' => $root_cats,
            'managed_cat' => $managed_cat,
            'cats_html' => $cats_html,
            'request_uri' => $_SERVER['REQUEST_URI'],
            'module_name' => $this->name,
            'l' => function ($string) {
                return $this->l($string);
            },
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/manage_cats2.tpl');
    }

    private function startImportAjax()
    {
        $current_file_name = array_reverse(explode('/', $_SERVER['SCRIPT_NAME']));
        $cron_url_search = Tools::getHttpHost(true, true) . __PS_BASE_URI__ .
            Tools::substr($_SERVER['SCRIPT_NAME'], Tools::strlen(__PS_BASE_URI__), -Tools::strlen($current_file_name['0'])) .
            'searchcron.php?full=1&token=' . Tools::getAdminToken($this->name);
        $cron_url_search_img = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'img/questionmark.png';

        $root_cats = $this->getRootCategories();
        $categories = [];
        foreach ($root_cats as $cat) {
            $categories[] = $cat['Cat1'];
        }

        $this->context->smarty->assign([
            'module_path' => $this->_path,
            'base_url' => Tools::getHttpHost(true) . __PS_BASE_URI__,
            'admin_token' => Tools::getAdminToken($this->name),
            'module_dir' => _MODULE_DIR_ . $this->name . '/',
            'categories' => $categories,
            'root_cats' => array_map(function ($cat) {
                return [
                    'Cat1' => $cat['Cat1'],
                    'description1' => $cat['description1'],
                    'selected_categories_array' => Configuration::get($this->name . '_' . $cat['Cat1'] . '_categories'),
                ];
            }, $root_cats),
            'slow_mode' => (Configuration::get($this->name . '_sync_slow') == 'on') ? 1 : 0,
            'l' => function ($string) {
                return $this->l($string);
            },
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/start_import_ajax.tpl');
    }
}
