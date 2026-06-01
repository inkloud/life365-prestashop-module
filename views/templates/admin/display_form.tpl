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

{* ===================== MAIN SETTINGS ===================== *}
<div class="panel">
    <div class="panel-heading">
        <img src="{$module_path|escape:'html':'UTF-8'}logo.gif" alt="" title="" /> {l s='Main settings' mod='life365' d='Modules.Life365.Admin'}
        <span class="badge pull-right">v{$module_version|escape:'html':'UTF-8'}</span>
    </div>

    <form action="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}" method="post" id="{$module_name|escape:'html':'UTF-8'}_form_settings" class="form-horizontal">

        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Select country' mod='life365' d='Modules.Life365.Admin'}</label>
            <div class="col-lg-6">
                <select id="{$module_name|escape:'html':'UTF-8'}_country" name="{$module_name|escape:'html':'UTF-8'}_country" class="form-control">
                    <option value="0">{l s='-- Choose a country --' mod='life365' d='Modules.Life365.Admin'}</option>
                    <option value="IT" {if $country_id == 'IT'}selected="selected"{/if}>Italy</option>
                    <option value="NL" {if $country_id == 'NL'}selected="selected"{/if}>Netherlands</option>
                    <option value="PT" {if $country_id == 'PT'}selected="selected"{/if}>Portugal</option>
                    <option value="ES" {if $country_id == 'ES'}selected="selected"{/if}>Spain</option>
                </select>
                <p class="help-block">
                    <a href="#" onclick="javascript:window.open('{$e_commerce_url[$country_id]|escape:'javascript':'UTF-8'}/user', '_blank');">{l s='Register new account' mod='life365' d='Modules.Life365.Admin'}</a>
                </p>
            </div>
        </div>

        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Life365 Login' mod='life365' d='Modules.Life365.Admin'}</label>
            <div class="col-lg-6">
                <input type="text" class="form-control" name="{$module_name|escape:'html':'UTF-8'}_login" id="{$module_name|escape:'html':'UTF-8'}_login" value="{$login|escape:'html':'UTF-8'}" />
            </div>
        </div>

        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Life365 Password' mod='life365' d='Modules.Life365.Admin'}</label>
            <div class="col-lg-6">
                <div class="input-group">
                    <input type="password" class="form-control" name="{$module_name|escape:'html':'UTF-8'}_password" id="{$module_name|escape:'html':'UTF-8'}_password" value="{$password|escape:'html':'UTF-8'}" />
                    <span class="input-group-btn">
                        <button type="button" class="btn btn-default" onclick="javascript:check_user_pwd($('#{$module_name|escape:'javascript':'UTF-8'}_login').val(), $('#{$module_name|escape:'javascript':'UTF-8'}_password').val(), $('#{$module_name|escape:'javascript':'UTF-8'}_country').val());">
                            <i class="icon-cogs"></i> {l s='Test' mod='life365' d='Modules.Life365.Admin'}
                        </button>
                    </span>
                </div>
                <div id="res_logon" style="margin-top:6px;"></div>
            </div>
        </div>

        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Default mark-up rate %' mod='life365' d='Modules.Life365.Admin'}</label>
            <div class="col-lg-6">
                <div class="input-group">
                    <input type="number" step="0.01" class="form-control" name="{$module_name|escape:'html':'UTF-8'}_overhead" value="{$overhead|escape:'html':'UTF-8'}" />
                    <span class="input-group-addon">%</span>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Default destination category' mod='life365' d='Modules.Life365.Admin'}</label>
            <div class="col-lg-6">
                <select id="{$module_name|escape:'html':'UTF-8'}_default_category" name="{$module_name|escape:'html':'UTF-8'}_default_category" class="form-control">
                    <option value="1">{l s='-- Choose a category --' mod='life365' d='Modules.Life365.Admin'}</option>
                    {foreach from=$categories item=category}
                        <option value="{$category.id_category|intval}" {if $category.selected}selected="selected"{/if}>
                            {for $i=0 to $category.level_depth*2}&nbsp;{/for}{$category.name|escape:'html':'UTF-8'}
                        </option>
                    {/foreach}
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Default tax' mod='life365' d='Modules.Life365.Admin'}</label>
            <div class="col-lg-6">
                <select name="{$module_name|escape:'html':'UTF-8'}_default_tax_id" class="form-control">
                    {foreach from=$tax_rules item=tax}
                        <option value="{$tax.id_tax_rules_group|intval}" {if $tax.selected}selected="selected"{/if}>
                            {$tax.name|escape:'html':'UTF-8'}
                        </option>
                    {/foreach}
                </select>
            </div>
        </div>

        <div class="panel-footer">
            <input type="submit" id="{$module_name|escape:'html':'UTF-8'}_submit" name="{$module_name|escape:'html':'UTF-8'}_submit" value="{l s='Update settings' mod='life365' d='Modules.Life365.Admin'}" class="btn btn-primary pull-right" />
        </div>
    </form>

    <div class="panel-footer">
        <form action="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}" method="post" id="{$module_name|escape:'html':'UTF-8'}_action_manage_cats" style="margin:0;">
            <button type="submit" name="{$module_name|escape:'html':'UTF-8'}_manage_cats" class="btn btn-default">
                <i class="icon-list"></i> {l s='Manage categories ...' mod='life365' d='Modules.Life365.Admin'}
            </button>
        </form>
    </div>
</div>

{* ===================== ACTION ===================== *}
<div class="panel">
    <div class="panel-heading">
        <img src="{$module_path|escape:'html':'UTF-8'}logo.gif" alt="" title="" /> {l s='Action' mod='life365' d='Modules.Life365.Admin'}
    </div>

    <form action="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}" method="post" id="{$module_name|escape:'html':'UTF-8'}_action_import">
        <button type="submit" name="{$module_name|escape:'html':'UTF-8'}_importer" class="btn btn-primary">
            <i class="icon-download"></i> {l s='Start import ...' mod='life365' d='Modules.Life365.Admin'}
        </button>
    </form>

    <hr />

    <h4>{l s='Cron urls by cateogry' mod='life365' d='Modules.Life365.Admin'}</h4>
    {if $root_cats|@count > 0}
        {foreach $root_cats as $cat}
            <div class="form-group">
                <label class="control-label col-lg-3">{$cat.description1|escape:'html':'UTF-8'}</label>
                <div class="col-lg-9">
                    <div class="input-group">
                        <input type="text" class="form-control life365-cron-url" readonly="readonly" onclick="this.select();" value="{$cron_url2|escape:'html':'UTF-8'}{$cat.Cat1|escape:'html':'UTF-8'}" />
                        <span class="input-group-btn">
                            <a href="{$cron_url2|escape:'html':'UTF-8'}{$cat.Cat1|escape:'html':'UTF-8'}" target="_blank" class="btn btn-default" title="{l s='Open in new tab' mod='life365' d='Modules.Life365.Admin'}">
                                <i class="icon-external-link"></i>
                            </a>
                            <button type="button" class="btn btn-default life365-copy-btn" title="{l s='Copy link' mod='life365' d='Modules.Life365.Admin'}">
                                <i class="icon-copy"></i>
                            </button>
                        </span>
                    </div>
                </div>
            </div>
        {/foreach}
    {else}
        <p class="alert alert-info">{l s='No categories available for cron URLs.' mod='life365' d='Modules.Life365.Admin'}</p>
    {/if}
    <p class="help-block">
        <a href="https://www.easycron.com/?ref=70609" target="_blank">{l s='A free CRON scheduler' mod='life365' d='Modules.Life365.Admin'}</a>
    </p>
</div>

{* ===================== OPTIONAL SETTINGS ===================== *}
<div class="panel">
    <div class="panel-heading">
        <img src="{$module_path|escape:'html':'UTF-8'}logo.gif" alt="" title="" /> {l s='Optional settings' mod='life365' d='Modules.Life365.Admin'}
    </div>

    <form action="{$smarty.server.REQUEST_URI|escape:'html':'UTF-8'}" method="post" id="{$module_name|escape:'html':'UTF-8'}_action_other_settings" class="form-horizontal">

        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Synchronize always' mod='life365' d='Modules.Life365.Admin'}</label>
            <div class="col-lg-9">
                {foreach $sync_options as $option}
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="{$option.name|escape:'html':'UTF-8'}" {if $option.checked}checked="checked"{/if} />
                            {$option.label|escape:'html':'UTF-8'}
                        </label>
                    </div>
                {/foreach}
            </div>
        </div>

        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Price limit' mod='life365' d='Modules.Life365.Admin'}</label>
            <div class="col-lg-9">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="{$module_name|escape:'html':'UTF-8'}_price_limit" {if $price_limit}checked="checked"{/if} />
                        {l s='Limits the price not to exceed the street-price' mod='life365' d='Modules.Life365.Admin'}
                    </label>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Debug' mod='life365' d='Modules.Life365.Admin'}</label>
            <div class="col-lg-9">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="{$module_name|escape:'html':'UTF-8'}_debug_mode" {if $debug_mode}checked="checked"{/if} />
                        {l s='Debug enabled' mod='life365' d='Modules.Life365.Admin'}
                    </label>
                </div>
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="{$module_name|escape:'html':'UTF-8'}_sync_slow" {if $sync_slow}checked="checked"{/if} />
                        {l s='Slow server' mod='life365' d='Modules.Life365.Admin'}
                    </label>
                </div>
            </div>
        </div>

        <div class="panel-footer">
            <input type="submit" name="{$module_name|escape:'html':'UTF-8'}_save_other_settings" value="{l s='Save optional settings' mod='life365' d='Modules.Life365.Admin'}" class="btn btn-primary pull-right" />
        </div>
    </form>
</div>

<script>
    $(function () {
        $('.life365-copy-btn').on('click', function () {
            var $input = $(this).closest('.input-group').find('.life365-cron-url');
            var $btn = $(this);
            var done = function () {
                var $icon = $btn.find('i');
                var old = $icon.attr('class');
                $icon.attr('class', 'icon-check');
                setTimeout(function () { $icon.attr('class', old); }, 1200);
            };
            $input[0].select();
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText($input.val()).then(done, function () { document.execCommand('copy'); done(); });
            } else {
                document.execCommand('copy');
                done();
            }
        });
    });

    function check_user_pwd(user1, password1, country1) {
        if (country1 == "0") {
            $("#res_logon").html("{l s='Select a country, please.' mod='life365' d='Modules.Life365.Admin'}");
        } else {
            $.ajaxSetup({ cache: false });
            var loadUrl = "{$check_logon_url|escape:'javascript':'UTF-8'}";
            $("#res_logon").html("<img src='{$loader_img_url|escape:'javascript':'UTF-8'}' />");

            $.ajax({
                type: "POST",
                url: loadUrl,
                dataType: "html",
                async: true,
                data: { u: user1, p: password1, c: country1 }
            }).done(function (msg) {
                $("#res_logon").html(msg);
            });
        }
    }
</script>
