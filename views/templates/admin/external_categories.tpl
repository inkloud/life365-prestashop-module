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
