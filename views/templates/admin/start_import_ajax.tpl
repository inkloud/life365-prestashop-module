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
<script>
    var todo_categories = {};
    var todo_categories_desc = {};
    var slow_mode = {$slow_mode};
</script>

{foreach from=$root_cats item=cat}
<fieldset>
    <legend>
        <img src="{$module_path}logo.gif" alt="" title="" />
        {l s='Action' mod='life365' d='Modules.Life365.Admin'}
    </legend>
    <div id="waiter_{$cat.Cat1}">
        <img src="{$base_url}img/loader.gif" alt="loading..." />
    </div>
    <div id="result_{$cat.Cat1}"></div>
</fieldset>
<script>
    todo_categories["{$cat.Cat1}"] = [{$cat.selected_categories_array}];
    todo_categories_desc["{$cat.Cat1}"] = "{$cat.description1}";
</script>
{/foreach}

<script>
    // Add jQuery Ajax Queue functionality
    jQuery.ajaxQueue = (function($) {
        var xhrQueue = [];
        var running = false;

        function processQueue() {
            if (running || !xhrQueue.length) {
                return;
            }
            running = true;
            var request = xhrQueue.shift();
            var xhr = $.ajax(request.settings);
            xhr.done(request.done)
               .fail(request.fail)
               .always(function() {
                   running = false;
                   processQueue();
               });
        }

        return function(settings) {
            var deferred = $.Deferred();
            xhrQueue.push({
                settings: settings,
                done: deferred.resolve,
                fail: deferred.reject
            });
            processQueue();
            return deferred.promise();
        };
    })(jQuery);

    function getProds1(k, loadUrl, selected_category, not_used, n_try) {
        var todo_cat = todo_categories[selected_category];
        for (g = 0; g < todo_cat.length; g++) {
            $.ajaxSetup({ cache: false });
            $.ajax({
                type: "POST",
                url: loadUrl,
                dataType: "html",
                data: { cat: todo_cat[g], offset: k, qty: 20 }
            }).done(function (msg) {
                if (msg.length > 0 && k < 10) {
                    $("#result_" + selected_category).append(msg + "<br />");
                    getProds1(k + 1, loadUrl, selected_category, 0, 0);
                } else {
                    console.log("END_Cat:" + todo_cat[g] + "Offset" + k + "Selected_category:" + selected_category);
                    $("#result_" + selected_category).append(msg + "<br />");
                }
            }).fail(function (msg, textStatus, errorThrown) {
                $("#result_" + selected_category).append("ERROR: " + textStatus + " - " + errorThrown + "<br />");
                console.log(errorThrown);
            });
        }
    }

    function getProds0(k, loadUrl, selected_category, g, n_try) {
        var todo_cat = todo_categories[selected_category];
        if (g < todo_cat.length) {
            var ajaxSettings = {
                type: "GET",
                url: loadUrl,
                dataType: "html",
                async: true,
                data: { cat: todo_cat[g], offset: k, qty: 20, action: "getProds", token: "{$admin_token}" }
            };
            
            var ajaxFunction = slow_mode ? $.ajaxQueue : $.ajax;
            
            ajaxFunction(ajaxSettings).done(function (msg) {
                if (msg.length > 0 && k < 10) {
                    console.log("cat:" + todo_cat[g] + "offset" + k + "selected_category:" + selected_category);
                    $("#result_" + selected_category).append(msg + "<br />");
                    getProds0(k + 1, loadUrl, selected_category, g, 0);
                } else {
                    console.log("END_Cat:" + todo_cat[g] + "Offset" + k + "Selected_category:" + selected_category);
                    $("#result_" + selected_category).append(msg + "<br />");
                    getProds0(0, loadUrl, selected_category, g + 1, 0);
                }
            }).fail(function (msg, textStatus, errorThrown) {
                $("#result_" + selected_category).append("ERROR: " + textStatus + " - " + errorThrown + "<br />");
                console.log(errorThrown);
                if (n_try < 5) {
                    $("#result_" + selected_category).append("Retrying " + todo_cat[g] + " (" + n_try + ") ...<br />");
                    getProds0(k, loadUrl, selected_category, g, n_try + 1);
                } else {
                    getProds0(0, loadUrl, selected_category, g + 1, 0);
                }
            });
        } else {
            console.log("waiter_" + selected_category);
            $("#waiter_" + selected_category).html("<b>Process completed!</b><br /><br />");
        }
    }

    function getProdsDisabled0(k, loadUrl, selected_category, g, n_try) {
        var ajaxSettings = {
            type: "GET",
            url: loadUrl,
            dataType: "html",
            async: true,
            data: { cat: selected_category, action: "disableProds", token: "{$admin_token}" }
        };
        
        var ajaxFunction = slow_mode ? $.ajaxQueue : $.ajax;
        
        ajaxFunction(ajaxSettings).done(function (msg) {
            console.log("Disabled products in: " + selected_category);
            $("#result_" + selected_category).append(msg + "<br />");
        }).fail(function (msg, textStatus, errorThrown) {
            $("#result_" + selected_category).append("ERROR: " + textStatus + " - " + errorThrown + "<br />");
            console.log(errorThrown);
            if (n_try < 5) {
                $("#result_" + selected_category).append("Retrying " + todo_cat[g] + " (" + n_try + ") ...<br />");
                getProdsDisabled0(k, loadUrl, selected_category, g, n_try + 1);
            }
        });
    }

    $(document).ready(function () {
        var loadUrl = "{$module_dir}ajax_importer.php";
        var selected_categories = {$categories|json_encode};

        for (var i = 0; i < selected_categories.length; i++) {
            $("#result_" + selected_categories[i]).append(
                "Job started. Category name <b>" +
                    todo_categories_desc[selected_categories[i]] +
                    "</b>, subcategory to import: <b>" +
                    todo_categories[selected_categories[i]].length +
                    "</b><br /><br />"
            );
            getProds0(0, loadUrl, selected_categories[i], 0);
            getProdsDisabled0(0, loadUrl, selected_categories[i], 0);
        }
    });
</script>