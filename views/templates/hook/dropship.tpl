{*
* 2007-2022 PrestaShop
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
*  @copyright  2007-2022 Giancarlo Spadini
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}
<div id="life365_dropshipping" align="right">
	<form method="GET" action="{$dropship_link|escape:'htmlall':'UTF-8'}" target="_blank">
		<input type="hidden" name="token" value="{$dropship_token|escape:'htmlall':'UTF-8'}" />
		<input type="hidden" name="id_o" value="{$dropship_order|escape:'htmlall':'UTF-8'}" />
		<input type="hidden" name="action" value="dropship" />
		<button type="submit" class="btn btn-primary" title="{l s='Click this link' mod='life365'}">
			<i class="icon-truck"></i>
			{l s='Copy dropship order on Life365' mod='life365'}
		</button>
	</form>
</div>
<br />