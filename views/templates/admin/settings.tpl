{* filepath: views/templates/admin/settings.tpl *}
<fieldset>
    <legend>
        <img src="{$module_path}logo.gif" alt="" title="" />
        {$l('Main settings')}
    </legend>
    <form action="{$request_uri}" method="post" id="{$module_name}_form_settings">
        <label>{$l('Select country')}</label>
        <div class="margin-form">
            <select id="{$module_name}_country" name="{$module_name}_country">
                <option value="0">{$l('-- Choose a country --')}</option>
                {foreach from=$countries item=country}
                    <option value="{$country.code}" {if $country.selected}selected="selected"{/if}>{$country.name}</option>
                {/foreach}
            </select>
            <a href="{$country.new_user}" target="_blank">{$l('Register new account')}</a>
        </div>
        <label>{$l('Life365 Login')}</label>
        <div class="margin-form">
            <input type="text" name="{$module_name}_login" id="{$module_name}_login" value="{$login}" />
        </div>
        <label>{$l('Life365 Password')}</label>
        <div class="margin-form">
            <input type="password" name="{$module_name}_password" id="{$module_name}_password" value="{$password}" />
        </div>
        <label>{$l('Default mark-up rate %')}</label>
        <div class="margin-form">
            <input type="number" step="0.01" name="{$module_name}_overhead" value="{$overhead}" />
        </div>
        <label>{$l('Default destination category')}</label>
        <div class="margin-form">
            <select id="{$module_name}_default_category" name="{$module_name}_default_category">
                {$categories_html}
            </select>
        </div>
        <label>{$l('Default tax')}</label>
        <div class="margin-form">
            <select name="{$module_name}_default_tax_id">
                {$tax_rules_html}
            </select>
        </div>
        <input type="submit" id="{$module_name}_submit" name="{$module_name}_submit" value="{$l('Update settings')}" class="button" />
    </form>
</fieldset>