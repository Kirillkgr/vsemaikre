{** Branding Text: replace product image in cart/checkout with saved preview for current user/session **}
{if $product.product_id}
    {assign var="bt_w" value=$settings.Thumbnails.product_cart_thumbnail_width|default:0}
    {assign var="bt_h" value=$settings.Thumbnails.product_cart_thumbnail_height|default:0}
    {assign var="bt_preview_url" value={"branding_text.preview_for_product?product_id=`$product.product_id`&w=`$bt_w`&h=`$bt_h`"|fn_url}}
    {assign var="bt_fallback_url" value=$product.main_pair.icon.image_path|default:$product.main_pair.detailed.image_path}
    <a href="{"products.view?product_id=`$product.product_id`"|fn_url}">
        <img
            class="ty-pict cm-image"
            src="{$bt_preview_url}"
            data-bt-preview-url="{$bt_preview_url}"
            data-bt-product-id="{$product.product_id}"
            {if $bt_w} width="{$bt_w}"{/if}
            {if $bt_h} height="{$bt_h}"{/if}
            style="{if $bt_w}width:{$bt_w}px; max-width:{$bt_w}px;{/if}{if $bt_h}height:{$bt_h}px; max-height:{$bt_h}px;{/if}object-fit:contain;"
            alt="{$product.product|default:fn_get_product_name($product.product_id)|escape}"
            onerror="this.onerror=null; this.src='{$bt_fallback_url|escape:'javascript'}'; {if $bt_w}this.style.width='{$bt_w}px'; this.style.maxWidth='{$bt_w}px';{/if}{if $bt_h}this.style.height='{$bt_h}px'; this.style.maxHeight='{$bt_h}px';{/if}this.style.objectFit='contain';"
        />
    </a>
{else}
    {$smarty.capture.default nofilter}
{/if}
