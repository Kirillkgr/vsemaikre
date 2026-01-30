<script type="text/javascript">
    (function (_, $) {
{literal}
        window.__BT_GLOBAL__ = window.__BT_GLOBAL__ || {};
        window.__BT_GLOBAL__.user_id = {/literal}{if $auth.user_id}{$auth.user_id}{else}0{/if}{literal};
        window.__BT_GLOBAL__.previewForProduct = '{/literal}{"branding_text.preview_for_product"|fn_url}{literal}';
{/literal}
    })(Tygh, Tygh.$);
</script>

{assign var="bt_ver" value="0.0.9"}
{script src="js/addons/branding_text/bt_core.js?v=`$bt_ver`"}
{if $smarty.request.bt_constructor == "Y"}
    {script src="js/addons/branding_text/designer.js?v=`$bt_ver`"}
{/if}

