{if $block.properties.products_links_type == "thumb"}
    <div class="ty-cart-items__list-item-image">
        <a href="{"products.view?product_id=`$product.product_id`"|fn_url}">
            {include
                file="common/image.tpl"
                images=$product.main_pair
                image_width=40
                image_height=40
                class="ty-pict cm-image"
                alt=$product.product|default:fn_get_product_name($product.product_id)
            }
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

