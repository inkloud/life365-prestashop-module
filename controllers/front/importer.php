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

/**
 * ModuleFrontController per PrestaShop 9+ compatibility
 * Rimpiazza l'accesso diretto a ajax_importer.php che è bloccato in PS9
 *
 * URL: /module/life365/importer?action=xxx&token=xxx
 */
class Life365ImporterModuleFrontController extends ModuleFrontController
{
    /**
     * Include il file ajax_importer.php e riutilizza tutte le sue funzioni
     */
    public function initContent()
    {
        // Imposta headers CORS come nell'originale
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST');
        header('Access-Control-Allow-Headers: X-Requested-With');

        // Aumenta il tempo di esecuzione
        ini_set('max_execution_time', 7200);

        // Include tutte le funzioni dal file ajax_importer.php
        require_once dirname(__FILE__) . '/../../ajax_importer_functions.php';

        // Gestisce l'azione richiesta
        $this->processAction();

        // Termina senza template
        exit;
    }

    /**
     * Processa l'azione richiesta (stesso comportamento di ajax_importer.php)
     */
    protected function processAction()
    {
        $action_token = Tools::getValue('token');
        $action = Tools::getValue('action');
        $opt_cat = Tools::getValue('cat');

        $module_name = getModuleInfo('name');

        // Genera token compatibile con tutte le versioni PS
        $expected_token = md5(_COOKIE_KEY_ . $module_name);

        // Azioni che non richiedono token
        $public_actions = ['version', 'getToken'];

        if (!in_array($action, $public_actions)) {
            // Verifica token per tutte le altre azioni
            if ($action_token !== $expected_token) {
                exit('Invalid token');
            }
        }

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

            case 'cron3':
                $mc = (int) Tools::getValue('mc');
                echo runCron3($mc);
                break;

            case 'disableProds':
                echo setProductsDisabled2($opt_cat);
                break;

            case 'version':
                echo getModuleInfo('user_app');
                echo '<br />';
                echo getModuleInfo('ps_version');
                break;

            case 'getToken':
                // Restituisce il token in formato JSON per uso da JavaScript
                header('Content-Type: application/json');
                echo json_encode(['token' => $expected_token]);
                break;

            default:
                echo 'Invalid action';
        }
    }
}
