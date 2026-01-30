{** Branding Text: replace product image in cart/checkout with saved preview for current user/session **}
{if $product.product_id}
    {assign var="bt_w" value=$settings.Thumbnails.product_cart_thumbnail_width|default:0}
    {assign var="bt_h" value=$settings.Thumbnails.product_cart_thumbnail_height|default:0}
    <a href="{"products.view?product_id=`$product.product_id`"|fn_url}">
        {include
            file="common/image.tpl"
            images=$product.main_pair
            image_width=$bt_w
            image_height=$bt_h
            class="ty-pict cm-image"
            alt=$product.product|default:fn_get_product_name($product.product_id)
        }
    </a>
{else}
    {$smarty.capture.default nofilter}
{/if}

