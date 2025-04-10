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
    <legend><img src="{$module_path}logo.gif" alt="" title="" />{l s='Main settings' mod='life365' d='Modules.Life365.Admin'}</legend>
    <form action="{$smarty.server.REQUEST_URI}" method="post" id="{$module_name}_form_settings">
        <label>{l s='Select country' mod='life365' d='Modules.Life365.Admin'}</label>
        <div class="margin-form">
            <select id="{$module_name}_country" name="{$module_name}_country">
                <option value="0">{l s='-- Choose a country --' mod='life365' d='Modules.Life365.Admin'}</option>
                <option value="IT" {if $country_id == 'IT'}selected="selected"{/if}>Italy</option>
                <option value="NL" {if $country_id == 'NL'}selected="selected"{/if}>Netherlands</option>
                <option value="PT" {if $country_id == 'PT'}selected="selected"{/if}>Portugal</option>
                <option value="ES" {if $country_id == 'ES'}selected="selected"{/if}>Spain</option>
            </select>
            <a href="#" onclick="javascript:window.open('{$e_commerce_url[$country_id]}/user', '_blank');">{l s='Register new account' mod='life365' d='Modules.Life365.Admin'}</a>
        </div>
        <label>{l s='Life365 Login' mod='life365' d='Modules.Life365.Admin'}</label>
        <div class="margin-form">
            <input type="text" name="{$module_name}_login" id="{$module_name}_login" value="{$login}" />
        </div>
        <label>{l s='Life365 Password' mod='life365' d='Modules.Life365.Admin'}</label>
        <div class="margin-form">
            <table>
                <tr>
                    <td>
                        <input type="password" name="{$module_name}_password" id="{$module_name}_password" value="{$password}" />
                    </td>
                    <td><div id="res_logon"></div></td>
                    <td>
                        <a href="#" onclick="javascript:check_user_pwd($('#{$module_name}_login').val(), $('#{$module_name}_password').val(), $('#{$module_name}_country').val());">{l s='Test' mod='life365' d='Modules.Life365.Admin'}</a>
                    </td>
                </tr>
            </table>
        </div>
        <label>{l s='Default mark-up rate %' mod='life365' d='Modules.Life365.Admin'}</label>
        <div class="margin-form">
            <input type="number" step="0.01" name="{$module_name}_overhead" value="{$overhead}" />
        </div>
        <label>{l s='Default destination category' mod='life365' d='Modules.Life365.Admin'}</label>
        <div class="margin-form">
            <select id="{$module_name}_default_category" name="{$module_name}_default_category">
                <option value="1">{l s='-- Choose a category --' mod='life365' d='Modules.Life365.Admin'}</option>
                {$categories}
            </select>
        </div>
        <label>{l s='Default tax' mod='life365' d='Modules.Life365.Admin'}</label>
        <div class="margin-form">
            <select name="{$module_name}_default_tax_id">
                {$tax_rules}
            </select>
        </div>
        <input type="submit" id="{$module_name}_submit" name="{$module_name}_submit" value="{l s='Update settings' mod='life365' d='Modules.Life365.Admin'}" class="button" />
    </form>
</fieldset>
