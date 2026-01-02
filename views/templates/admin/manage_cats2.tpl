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
    <legend><img src="{$module_path}logo.gif" alt="" title="" />{l s='Manage categories to import' mod='life365' d='Modules.Life365.Admin'}</legend>
    {if $root_cats|@count > 0}
        <div id="tabs">
            <ul>
                {foreach $root_cats as $cat}
                    {if $cat.Cat1 == $managed_cat}
                        <li><a href="#tabs-{$cat.Cat1}">{$cat.description1}</a></li>
                    {/if}
                {/foreach}
            </ul>
            {foreach $root_cats as $cat}
                {if $cat.Cat1 == $managed_cat}
                    <div id="tabs-{$cat.Cat1}">
                        <form action="{$request_uri}" method="post" id="{$module_name}_action_cats-{$cat.Cat1}">
                            <div class="margin-form">
                                {$cats_html}
                            </div>
                            <input class="button" type="button" onclick="history.back()" value="{l s='Cancel' mod='life365' d='Modules.Life365.Admin'}" />
                            <input type="submit" name="{$module_name}_action_cats_b" value="{l s='Update settings' mod='life365' d='Modules.Life365.Admin'}" class="button" />
                        </form>
                    </div>
                {/if}
            {/foreach}
        </div>
    {else}
        <p>{l s='No categories available to manage.' mod='life365' d='Modules.Life365.Admin'}</p>
    {/if}
</fieldset>
