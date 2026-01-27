<script type="text/javascript">
    (function (_, $) {
{literal}
        window.__BT_GLOBAL__ = window.__BT_GLOBAL__ || {};
        window.__BT_GLOBAL__.user_id = {/literal}{if $auth.user_id}{$auth.user_id}{else}0{/if}{literal};
        window.__BT_GLOBAL__.previewForProduct = '{/literal}{"branding_text.preview_for_product"|fn_url}{literal}';
{/literal}
    })(Tygh, Tygh.$);
</script>

{script src="js/addons/branding_text/bt_core.js"}
{script src="js/addons/branding_text/bt_preview.js"}
{script src="js/addons/branding_text/designer.js"}
