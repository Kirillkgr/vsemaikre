{** Branding Text: minicart product row with preview image (owner = current user/session) **}
{assign var="bt_preview_url" value={"branding_text.preview_for_product?product_id=`$product.product_id`&w=40&h=40"|fn_url}}
{assign var="bt_fallback_url" value=$product.main_pair.icon.image_path|default:$product.main_pair.detailed.image_path}

{if $block.properties.products_links_type == "thumb"}
    <div class="ty-cart-items__list-item-image">
        <a href="{"products.view?product_id=`$product.product_id`"|fn_url}">
            <img class="ty-pict cm-image" src="{$bt_fallback_url}" data-bt-preview-url="{$bt_preview_url}" data-bt-product-id="{$product.product_id}" width="40" height="40" style="width:40px; height:40px; max-width:40px; max-height:40px; object-fit:contain;" alt="{$product.product|default:fn_get_product_name($product.product_id)|escape}" onerror="this.onerror=null; this.src='{$bt_fallback_url|escape:'javascript'}'; this.style.width='40px'; this.style.height='40px'; this.style.maxWidth='40px'; this.style.maxHeight='40px'; this.style.objectFit='contain';" />
        </a>
    </div>
{/if}

<div class="ty-cart-items__list-item-desc">
    <a href="{"products.view?product_id=`$product.product_id`"|fn_url}">{$product.product|default:fn_get_product_name($product.product_id) nofilter}</a>
    <p>
        <span>{$product.amount}</span><span>&nbsp;x&nbsp;</span>{include file="common/price.tpl" value=$product.display_price span_id="price_`$key`_`$dropdown_id`" class="none"}
    </p>
</div>

{if $block.properties.display_delete_icons == "Y"}
    <div class="ty-cart-items__list-item-tools cm-cart-item-delete">
        {if (!$runtime.checkout || $force_items_deletion) && !$product.extra.exclude_from_calculate}
            {include file="buttons/button.tpl" but_href="checkout.delete.from_status?cart_id=`$key`&redirect_url=`$r_url`" but_meta="cm-ajax cm-ajax-full-render" but_target_id="cart_status*" but_role="delete" but_name="delete_cart_item"}
        {/if}
    </div>
{/if}
