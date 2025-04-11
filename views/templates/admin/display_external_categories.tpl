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
<table>
    <tr>
        <th>{strtoupper($module_name)} {l s='category' mod='life365' d='Modules.Life365.Admin'}</th>
        <th>{l s='Local category' mod='life365' d='Modules.Life365.Admin'}</th>
        <th>{l s='Profit' mod='life365' d='Modules.Life365.Admin'}</th>
    </tr>
    {foreach $remote_tree_category as $cat_level2}
        <tr>
            <td colspan="3">
                <b>{$root_category_name}::{$cat_level2['name']}</b>
            </td>
        </tr>
        {foreach $cat_level2['zchildren'] as $cat}
            {assign var="local_cat" value=""}
            {foreach $categories as $category}
                {if $category.cat3 == $cat['id']}
                    {assign var="local_cat" value=$category}
                {/if}
            {/foreach}
            <tr>
                <td>
                    <input type="checkbox" name="{$module_name}_categories[]" value="{$local_cat['cat3']}" {if $local_cat['checked']}checked{/if} />
                    {$cat['name']}
                </td>
                <td>
                    <select name="cat_ps_{$local_cat['cat3']}" class="children_cats_select" data-selected-id="{$local_cat['id_cat_ps']}">
                        {$all_categories}
                    </select>
                </td>
                <td>
                    <input type="number" step="0.01" value="{$local_cat['profit']}" name="profit_{$local_cat['cat3']}" placeholder="{l s='profit' mod='life365' d='Modules.Life365.Admin'}" />%
                </td>
            </tr>
        {/foreach}
    {/foreach}
</table>
<input type="hidden" name="{$module_name}_cat1" value="{$root_category}" />
<input type="hidden" name="{$module_name}_list_cat3" value="{$list_cat3}" />

<select style="display:none;" id="all_categories">
    {$all_categories}
</select>

<script>
    $(function() {
        var all_cats = $('#all_categories').html();
        $(".children_cats_select").each(function() {
            var original_name = $(this).attr('name');
            var sel = $(this).attr('data-selected-id');
            $(this).html(all_cats);
            $(this).attr('name', original_name);
            $(this).val(sel);
        });
    });
</script>
