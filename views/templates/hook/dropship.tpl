<div id="life365_dropshipping" align="right">
	<form method="GET" action="{$dropship_link|escape:'htmlall':'UTF-8'}" target="_blank">
		<input type="hidden" name="token" value="{$dropship_token}" />
		<input type="hidden" name="id_o" value="{$dropship_order}" />
		<input type="hidden" name="action" value="dropship" />
		<button type="submit" class="btn btn-primary" title="{l s='Click this link' mod='life365'}">
			<i class="icon-truck"></i>
			{l s='Copy dropship order on Life365' mod='life365'}
		</button>
	</form>
</div>
<br />