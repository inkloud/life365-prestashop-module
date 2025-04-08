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
        <th>{$module_name|strtoupper} {$l('category')}</th>
        <th>{$l('Local category')}</th>
        <th>{$l('Profit')}</th>
    </tr>
    {foreach $categories as $category}
        <tr>
            <td>
                <input type="checkbox" name="{$module_name}_categories[]" value="{$category.cat3}" {if $category.checked}checked{/if} />
                {$category.description3}
            </td>
            <td>
                <select name="cat_ps_{$category.cat3}" class="children_cats_select" data-selected-id="{$category.id_cat_ps}">
                    <!-- Options will be populated dynamically -->
                </select>
            </td>
            <td>
                <input type="number" step="0.01" value="{$category.profit}" name="profit_{$category.cat3}" placeholder="{$l('profit')}" />%
            </td>
        </tr>
    {/foreach}
</table>
<input type="hidden" name="{$module_name}_cat1" value="{$root_category}" />
<input type="hidden" name="{$module_name}_list_cat3" value="{$list_cat3}" />
