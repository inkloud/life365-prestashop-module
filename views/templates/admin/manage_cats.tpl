{*
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
*  @author Giancarlo Spadini <giancarlo@spadini.it>
*  @copyright  2007-2026 Giancarlo Spadini
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
<fieldset>
    <legend><img src="{$module_path|escape:'html':'UTF-8'}logo.gif" alt="" title="" />{l s='Manage categories to import' mod='life365' d='Modules.Life365.Admin'}</legend>
    {if $root_cats|@count > 0}
        <div class="col-sm-5">
            {foreach $root_cats as $cat}
                <div>
                    <form action="{$request_uri|escape:'html':'UTF-8'}" method="post" id="{$module_name|escape:'html':'UTF-8'}_action_cats-{$cat.Cat1|escape:'html':'UTF-8'}">
                        <input type="hidden" name="{$module_name|escape:'html':'UTF-8'}_cat_click" value="{$cat.Cat1|escape:'html':'UTF-8'}" />
                        <input type="submit" name="{$module_name|escape:'html':'UTF-8'}_action_cat_click" value="{l s='Manage category' mod='life365' d='Modules.Life365.Admin'} {$cat.description1|escape:'html':'UTF-8'}" class="button" />
                    </form>
                </div>
            {/foreach}
        </div>
    {else}
        <p>{l s='No categories available to manage.' mod='life365' d='Modules.Life365.Admin'}</p>
    {/if}
</fieldset>
