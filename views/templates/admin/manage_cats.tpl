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
