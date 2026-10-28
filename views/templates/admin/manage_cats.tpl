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
<div class="life365-admin">
    <div class="life365-panel">
        <div class="life365-panel-heading">
            <img src="{$module_path|escape:'html':'UTF-8'}logo.gif" alt="" title="" />
            {l s='Manage categories to import' mod='life365' d='Modules.Life365.Admin'}
        </div>

        <div class="life365-panel-body">
            {if $root_cats|@count > 0}
                <div class="life365-cat-buttons">
                    {foreach $root_cats as $cat}
                        <form action="{$request_uri|escape:'html':'UTF-8'}" method="post" id="{$module_name|escape:'html':'UTF-8'}_action_cats-{$cat.Cat1|escape:'html':'UTF-8'}">
                            <input type="hidden" name="{$module_name|escape:'html':'UTF-8'}_cat_click" value="{$cat.Cat1|escape:'html':'UTF-8'}" />
                            <button type="submit" name="{$module_name|escape:'html':'UTF-8'}_action_cat_click" class="life365-btn">
                                <i class="icon-list"></i> {l s='Manage category' mod='life365' d='Modules.Life365.Admin'} {$cat.description1|escape:'html':'UTF-8'}
                            </button>
                        </form>
                    {/foreach}
                </div>
            {else}
                <p class="life365-alert">{l s='No categories available to manage.' mod='life365' d='Modules.Life365.Admin'}</p>
            {/if}
        </div>
    </div>
</div>
