{** Branding Text: кнопка рядом с "В корзину" (зелёная), отображается только на странице товара **}
{if $product && $product.product_id && $runtime.controller == 'products' && $runtime.mode == 'view'}
  <div class="ty-branding-text" style="margin-top:8px;">
    <a class="ty-btn ty-btn__primary"
       href="{fn_url("branding_text.constructor?product_id=`$product.product_id`")}"
       onclick="alert('Запускается адон');"
       rel="nofollow">
      Брендировать товар
    </a>
  </div>
{/if}
