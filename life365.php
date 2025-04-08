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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/ProductImporter.php';
require_once dirname(__FILE__) . '/AccessoryImporter.php';

class Life365 extends Module
{
    private $c_api_url = 'https://api.life365.eu/v2.php';
    private $c_html = '';

    public function __construct()
    {
        $this->name = 'life365';
        $this->tab = 'quick_bulk_update';
        $this->version = '8.0.97';
        $this->author = 'Giancarlo Spadini';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = [
            'min' => '1.6.0.4',
            'max' => '8.2.0',
        ];
        $this->module_key = '17fe516516b4f12fb1d877a3600dbedc';

        parent::__construct();

        $this->displayName = $this->l('Life365/Inkloud dropshipping');
        $this->description = $this->l('Expand your shop. Start now selling over 10.000 products in dropshipping!');
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
        if (!parent::enable($forceAll)) {
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
        return (
            $this->registerHook('backOfficeHome')
            && $this->registerHook('DisplayBackOfficeHeader')
            && $this->registerHook('displayAdminOrderTabOrder')
            && $this->registerHook('displayAdminOrderRight')
            && $this->registerHook('displayAdminOrderSide')
        );
    }

    private function unregisterHooks()
    {
        return true;
    }

    public function hookDisplayAdminOrderTabOrder($params)
    {
        if (_PS_VERSION_ >= '1.7.7.0') {
            $order_id = $params['id_order'];
        } else { // older versions
            $order_id =  $params['order']->id;
        }

        $this->smarty->assign([
            'order' => $params['order'],
            'dropship_link' => $this->_path.'ajax_importer.php',
            'dropship_order' => $order_id,
            'dropship_token' => Tools::getAdminToken($this->name)
        ]);

        return $this->display(__FILE__, 'views/templates/hook/dropship.tpl');
    }
    
    public function hookdisplayAdminOrderRight($params)
    {
        if (_PS_VERSION_ >= '1.7.7.0') {
            $order_id = $params['id_order'];
        } else { // older versions
            $order_id =  $params['order']->id;
        }
        $this->smarty->assign([
            'order' => $params['order'],
            'dropship_link' => $this->_path.'ajax_importer.php',
            'dropship_order' => $order_id,
            'dropship_token' => Tools::getAdminToken($this->name)
        ]);

        return $this->display(__FILE__, 'views/templates/hook/dropship.tpl');
    }

    public function hookDisplayAdminOrderSide($params)
    {
        if (_PS_VERSION_ >= '1.7.7.0') {
            $order_id = $params['id_order'];
        } else { // older versions
            $order_id =  $params['order']->id;
        }
        $this->smarty->assign(array('order' => $params['order'],
        'dropship_link' => $this->_path.'ajax_importer.php',
        'dropship_order' => $order_id,
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

    private function postProcess()
    {
        Configuration::updateValue($this->name.'_country', Tools::getValue($this->name.'_country'));
        Configuration::updateValue($this->name.'_login', Tools::getValue($this->name.'_login'));
        Configuration::updateValue($this->name.'_password', Tools::getValue($this->name.'_password'));
        Configuration::updateValue($this->name.'_overhead', Tools::getValue($this->name.'_overhead'));
        Configuration::updateValue($this->name.'_default_category', Tools::getValue($this->name.'_default_category'));
        Configuration::updateValue($this->name.'_default_tax_id', Tools::getValue($this->name.'_default_tax_id'));
    }

    public function getContent()
    {
        if (Tools::isSubmit($this->name.'_importer')) {
            return $this->startImportAjax();
        }

        if (Tools::isSubmit($this->name.'_action_cat_click')) {
            $managed_cat = Tools::getValue($this->name.'_cat_click');
            return $this->manageCats2($managed_cat);
        }

        if (Tools::isSubmit($this->name.'_manage_cats')) {
            return $this->manageCats();
        }

        if (Tools::isSubmit($this->name.'_action_cats_b')) {
            $cat1 = Tools::getValue($this->name.'_cat1');
            $cats_array = Tools::getValue($this->name.'_categories');
            foreach ($cats_array as $cat) {
                $profit = Tools::getValue('profit_'.$cat) * 100;
                $ps_cat = Tools::getValue('cat_ps_'.$cat);

                Db::getInstance()->delete($this->name.'_category', 'id_category_external = '.(int)$cat);
                Db::getInstance()->insert($this->name.'_category', array(
                    'id_category_external' => (int)$cat,
                    'profit' => (int)$profit / 100,
                    'id_category_ps' => (int)$ps_cat
                ));
            }
            Configuration::updateValue(
                $this->name.'_'.$cat1.'_categories',
                implode(',', Tools::getValue($this->name.'_categories'))
            );
            // Force update of all products
            $t_sql = 'UPDATE `'._DB_PREFIX_.'life365_product` SET `version` = 0;';
            Db::getInstance()->execute($t_sql);
        }

        if (Tools::isSubmit($this->name.'_save_other_settings')) {
            Configuration::updateValue($this->name.'_sync_name', Tools::getValue($this->name.'_sync_name'));
            Configuration::updateValue($this->name.'_sync_short_desc', Tools::getValue($this->name.'_sync_short_desc'));
            Configuration::updateValue($this->name.'_sync_desc', Tools::getValue($this->name.'_sync_desc'));
            Configuration::updateValue($this->name.'_sync_price', Tools::getValue($this->name.'_sync_price'));
            Configuration::updateValue($this->name.'_sync_category', Tools::getValue($this->name.'_sync_category'));
            Configuration::updateValue($this->name.'_debug_mode', Tools::getValue($this->name.'_debug_mode'));
            Configuration::updateValue($this->name.'_price_limit', Tools::getValue($this->name.'_price_limit'));
            // Configuration::updateValue($this->name.'_parent_categories', Tools::getValue($this->name.'_parent_categories'));
        }

        $output = '';
        if (Tools::isSubmit($this->name.'_submit')) {
            $this->postProcess();
            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }
        $output .= $this->displayForm();

        return $output;
    }

    private function getCatetoryDepth($id_category)
    {
        $level_depth = Db::getInstance()->getValue(
            'SELECT level_depth
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
        foreach ($result as $tax) {
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
            $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $protocol = 'https://';
        } else {
            $protocol = 'http://';
        }
        
        $domainName = $_SERVER['HTTP_HOST'];

        return $protocol.$domainName;
    }

    private function displayCatetoriesChildren($id_parent, $id_selected = 1, $id_lang = 1)
    {
        $result_html = '';
        $result = Category::getChildren($id_parent, $id_lang, true);
        foreach ($result as $cat) {
            $level_depth = $this->getCatetoryDepth($cat['id_category']);
            $result_html .= '<option value="'.$cat['id_category'].'"'.(($id_selected == $cat['id_category']) ? ' selected="selected"' : '').'>';
            $result_html .= str_repeat('&nbsp;', $level_depth * 2).$cat['name'];
            $result_html .= '</option>';
            $result_html .= $this->displayCatetoriesChildren($cat['id_category'], $id_selected, $id_lang);
        }
        return $result_html;
    }

    private function displayExternalCategories($root_category = 0)
    {
        $selected_categories_array = explode(',', Configuration::get($this->name . '_' . $root_category . '_categories'));
        $available_cats = $this->availableCategories();
        $categories = [];

        if (is_array($available_cats)) {
            foreach ($available_cats as $cat) {
                if ($root_category == 0 || $cat['Cat1'] == $root_category) {
                    $categories[] = [
                        'cat3' => $cat['Cat3'],
                        'description3' => $cat['description3'],
                        'checked' => in_array($cat['Cat3'], $selected_categories_array),
                        'profit' => $this->getCategoryProfit($cat['Cat3']),
                        'id_cat_ps' => $this->getCategoryPS($cat['Cat3']),
                    ];
                }
            }
        }

        $this->context->smarty->assign([
            'categories' => $categories,
            'root_category' => $root_category,
            'list_cat3' => implode(',', array_column($categories, 'cat3')),
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/external_categories.tpl');
    }

    private function displayForm()
    {
        $countries = [
            ['code' => 'IT', 'name' => 'Italy', 'selected' => Configuration::get($this->name.'_country') == 'IT', 'new_user' => 'https://www.life365.eu/user'],
            ['code' => 'NL', 'name' => 'Netherlands', 'selected' => Configuration::get($this->name.'_country') == 'NL', 'new_user' => 'https://www.inkloud.eu/user'],
            ['code' => 'PT', 'name' => 'Portugal', 'selected' => Configuration::get($this->name.'_country') == 'PT', 'new_user' => 'https://www.life365.pt/user'],
            ['code' => 'ES', 'name' => 'Spain', 'selected' => Configuration::get($this->name.'_country') == 'ES', 'new_user' => 'https://www.inkloud.es/user'],
        ];
    
        $this->context->smarty->assign([
            'module_name' => $this->name,
            'module_path' => $this->_path,
            'request_uri' => $_SERVER['REQUEST_URI'],
            'login' => Configuration::get($this->name.'_login'),
            'password' => Configuration::get($this->name.'_password'),
            'overhead' => Configuration::get($this->name.'_overhead'),
            'categories_html' => $this->displayCatetoriesChildren(Configuration::get('PS_HOME_CATEGORY'), Configuration::get($this->name.'_default_category'), Configuration::get('PS_LANG_DEFAULT')),
            'tax_rules_html' => $this->displayTaxRules(Configuration::get($this->name.'_default_tax_id')),
            'countries' => $countries,
            'l' => function ($string) {
                return $this->l($string);
            },
        ]);
    
        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/settings.tpl');
    }

    private function getAccessToken()
    {
        $country_id = Configuration::get($this->name.'_country');
        $login = Configuration::get($this->name.'_login');
        $password = Configuration::get($this->name.'_password');
        $referer = $_SERVER['HTTP_HOST'];
        $user_app = 'PrestaShop module ver: '.$this->version;

        if (function_exists('curl_init')) {
            $con = curl_init();
            $url = $this->c_api_url.'?f=getToken';
            $my_values = array('country_id' => $country_id, 'login' => $login, 'password' => $password, 'referer' => $referer, 'user_app' => $user_app);

            curl_setopt($con, CURLOPT_URL, $url);
            curl_setopt($con, CURLOPT_POST, true);
            curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
            curl_setopt($con, CURLOPT_HEADER, false);
            curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

            $res_curl = curl_exec($con);
            curl_close($con);

            $res = json_decode($res_curl, true);

            if ($res['response_code'] == '1') {
                $token = $res['response_detail'];
            } else {
                $token = false;
            }

            return $token;
        } else {
            return false;
        }
    }

    private function getRootCategories()
    {
        $available_cats = $this->availableCategories();
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

    private function availableCategories()
    {
        $access_token = $this->getAccessToken();

        if (function_exists('curl_init')) {
            $con = curl_init();
            $url = $this->c_api_url.'?f=getCategories&access_token='.$access_token;
            $my_values = array();

            curl_setopt($con, CURLOPT_URL, $url);
            curl_setopt($con, CURLOPT_POST, true);
            curl_setopt($con, CURLOPT_POSTFIELDS, $my_values);
            curl_setopt($con, CURLOPT_HEADER, false);
            curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($con, CURLOPT_SSL_VERIFYPEER, false);

            $res_curl = curl_exec($con);
            curl_close($con);

            $res = json_decode($res_curl, true);

            if ($res['response_code'] == '1') {
                return $res['response_detail'];
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
            'root_cats' => $root_cats,
            'request_uri' => $_SERVER['REQUEST_URI'],
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/manage_cats.tpl');
    }

    private function manageCats2($managed_cat)
    {
        $result_html = '';
        $root_cats = $this->getRootCategories();

        $result_html .= '<fieldset><legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Manage categories to import').'</legend>';
        if (is_array($root_cats)) {
            $result_html .= '
            <div id="tabs">
                      <ul>';
            foreach ($root_cats as $cat) {
                if ($cat['Cat1'] == $managed_cat) {
                    $result_html .= '<li><a href="#tabs-'.$cat['Cat1'].'">'.$cat['description1'].'</a></li>';
                }
            }
            $result_html .= '</ul>';

            $cats_html = $this->displayExternalCatetories($managed_cat);

            foreach ($root_cats as $cat) {
                $result_html .= '<div id="tabs-'.$cat['Cat1'].'">
                    <form action="'.$_SERVER['REQUEST_URI'].'" method="post" id="'.$this->name.'_action_cats-'.$cat['Cat1'].'">
                        <div class="margin-form">
                            '.$cats_html.'
                        </div>
                        <input class="button" type="button" onclick="history.back()" value="'.$this->l('Cancel').'"></input>
                        <input type="submit" name="'.$this->name.'_action_cats_b" value="'.$this->l('Update settings').'" class="button" />
                    </form>
                </div>';
            }
        }
        $result_html .= '
            </div>
        </fieldset>';

        return $result_html;
    }

    private function cronUrl2()
    {
        $result_html = '';
        $root_cats = $this->getRootCategories();

        $cron_url2 = Tools::getHttpHost(true).__PS_BASE_URI__.'modules/'.$this->name.'/ajax_importer.php?action=cron2&token='.Tools::getAdminToken($this->name).'&mc=';

        if (is_array($root_cats)) {
            $result_html .= '<div class="col-sm-5">';
            foreach ($root_cats as $cat) {
                $result_html .= '<div><i>'.$cat['description1'].':</i><br>&nbsp&nbsp'.$cron_url2.$cat['Cat1'].'<br></div>';
            }
            $result_html .= '</div>';
        }

        return $result_html;
    }


    private function startImportAjax()
    {
        $result_html = '';

        $current_file_name = array_reverse(explode('/', $_SERVER['SCRIPT_NAME']));
        $cron_url_search = Tools::getHttpHost(true, true).__PS_BASE_URI__.
            Tools::substr($_SERVER['SCRIPT_NAME'], Tools::strlen(__PS_BASE_URI__), -Tools::strlen($current_file_name['0'])).
            'searchcron.php?full=1&token='.Tools::getAdminToken($this->name);
        $cron_url_search_img = Tools::getHttpHost(true).__PS_BASE_URI__.'img/questionmark.png';

        // Resume category from where we left off
        $root_cats = $this->getRootCategories();

        $result_html .= '
            <script>
            var todo_categories = {};
            var todo_categories_desc = {};
            </script>
        ';

        $categories = array();
        foreach ($root_cats as $cat) {
            $categories[] = $cat['Cat1'];
            $selected_categories_array = Configuration::get($this->name.'_'.$cat['Cat1'].'_categories');
            $result_html .= '
            <fieldset><legend><img src="'.$this->_path.'logo.gif" alt="" title="" />'.$this->l('Action').'</legend>
                <div id="waiter_'.$cat['Cat1'].'"><img src="'.Tools::getHttpHost(true).__PS_BASE_URI__.'img/loader.gif" alt="loading..." /></div>
                <div id="result_'.$cat['Cat1'].'"></div>
            </fieldset>
            <script>
               todo_categories['.$cat['Cat1'].'] = ['.$selected_categories_array.'];
			   todo_categories_desc['.$cat['Cat1'].'] = "'.$cat['description1'].'";
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
                                type: "GET",
                                url: loadUrl,
                                dataType : "html",
                                async: true,
                                data: {cat: todo_cat[g], offset: k, qty: 20, action: "getProds", token: "'.Tools::getAdminToken($this->name).'"}
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
//                      $("#waiter_"+selected_categories[i]).html("<b>Process completed!</b><br /><br />");
//                      $("#result_"+selected_categories[i]).append("<br /><br /><a href=\"'.$cron_url_search.'\" target=\"_blank\"><img src=\"'.$cron_url_search_img.'\">Click here to create a search index for new products<img src=\"'.$cron_url_search_img.'\"></a><br />");
                    }
                }
                
                function getProdsDisabled0(k, loadUrl, selected_category, g, n_try)
                {
                    $.ajax({
                            type: "GET",
                            url: loadUrl,
                            dataType : "html",
                            async: true,
                            data: {cat: selected_category, action: "disableProds", token: "'.Tools::getAdminToken($this->name).'"}
                        }).done(function( msg ) {
                            console.log("Disabled products in: " + selected_category);
                            $("#result_"+selected_category).append(msg + "<br />");
                        })
                        .fail(function (msg, textStatus, errorThrown) {
                            $("#result_"+selected_category).append("ERROR: " + textStatus + " - " + errorThrown + "<br />");
                            console.log(errorThrown);
                            if (n_try<5)
                            {
                                $("#result_"+selected_category).append("Retrying "+todo_cat[g]+" ("+n_try+") ...<br />");
                                getProdsDisabled0(k, loadUrl, selected_category, g, n_try+1);
                            }
                        })
                }

            $(document).ready(function() {
                var loadUrl = "'. _MODULE_DIR_ . $this->name . '/ajax_importer.php";

                var selected_categories = '.json_encode($categories).'

                for (var i=0;i<selected_categories.length;i++)
                {
                    $("#result_"+selected_categories[i]).append("Job started. Category name <b>"+todo_categories_desc[selected_categories[i]]+"</b>, subcategory to import: <b>"+todo_categories[selected_categories[i]].length+"</b><br /><br />");
                    getProds0(0, loadUrl, selected_categories[i], 0);
                    getProdsDisabled0(0, loadUrl, selected_categories[i], 0);
                }
            });

            </script>
        ';

        return $result_html;
    }

    private function getCategoryProfit($categoryId)
    {
        // Fetch the profit for the given category from the database
        return Db::getInstance()->getValue(
            'SELECT profit FROM `'._DB_PREFIX_.$this->name.'_category` WHERE id_category_external = '.(int)$categoryId
        );
    }

    private function getCategoryPS($categoryId)
    {
        // Fetch the PrestaShop category ID for the given external category
        return Db::getInstance()->getValue(
            'SELECT id_category_ps FROM `'._DB_PREFIX_.$this->name.'_category` WHERE id_category_external = '.(int)$categoryId
        );
    }

    private function displayExternalCatetories($managed_cat)
    {
        // Generate HTML for displaying external categories
        $selected_categories_array = explode(',', Configuration::get($this->name . '_' . $managed_cat . '_categories'));
        $available_cats = $this->availableCategories();
        $categories = [];

        if (is_array($available_cats)) {
            foreach ($available_cats as $cat) {
                if ($cat['Cat1'] == $managed_cat) {
                    $categories[] = [
                        'cat3' => $cat['Cat3'],
                        'description3' => $cat['description3'],
                        'checked' => in_array($cat['Cat3'], $selected_categories_array),
                        'profit' => $this->getCategoryProfit($cat['Cat3']),
                        'id_cat_ps' => $this->getCategoryPS($cat['Cat3']),
                    ];
                }
            }
        }

        $this->context->smarty->assign([
            'categories' => $categories,
            'root_category' => $managed_cat,
            'list_cat3' => implode(',', array_column($categories, 'cat3')),
        ]);

        return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/external_categories.tpl');
    }
}
