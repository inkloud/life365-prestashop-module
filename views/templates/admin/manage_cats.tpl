{*
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
*  @author Giancarlo Spadini <giancarlo@spadini.it>
*  @copyright  2007-2025 Giancarlo Spadini
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
<fieldset>
    <legend><img src="{$module_path}logo.gif" alt="" title="" />{$l('Manage categories to import')}</legend>
    <div class="col-sm-5">
        {foreach $root_cats as $cat}
            <div>
                <form action="{$request_uri}" method="post" id="{$module_name}_action_cats-{$cat.Cat1}">
                    <input type="hidden" name="{$module_name}_cat_click" value="{$cat.Cat1}" />
                    <input type="submit" name="{$module_name}_action_cat_click" value="{$l('Manage category')} {$cat.description1}" class="button" />
                </form>
            </div>
        {/foreach}
    </div>
</fieldset>
