{if $smarty.request.bw_preview|default:''}
    <meta name="bw-colors" content="bg={$bw_background_color|default:''|escape};accent={$bw_accent_color|default:''|escape};text={$bw_text_color|default:''|escape}" />
{/if}

{if $bw_background_color|default:'' || $bw_accent_color|default:'' || $bw_text_color|default:''}
<style>
    :root {
        {if $bw_background_color|default:''}--bw-background-color: {$bw_background_color|escape};{/if}
        {if $bw_accent_color|default:''}--bw-accent-color: {$bw_accent_color|escape};{/if}
        {if $bw_text_color|default:''}--bw-text-color: {$bw_text_color|escape};{/if}
    }

    {if $bw_text_color|default:''}
    body {
        color: var(--bw-text-color) !important;
    }

    .ty-mainbox-title,
    .ty-breadcrumbs,
    .ty-breadcrumbs__a,
    .ty-breadcrumbs__current,
    .ty-product-block-title,
    .ty-grid-list__item-name,
    .ty-product-list__item-name,
    .ty-cart-content__title,
    .ty-checkout__title,
    .ty-account-info__title,
    .ty-tabs__item,
    .ty-subheader {
        color: var(--bw-text-color) !important;
    }
    {/if}

    {if $bw_background_color|default:''}
    body {
        background: var(--bw-background-color) !important;
    }

    .tygh-top-panel,
    .tygh-header,
    .tygh-content,
    .tygh-footer,
    .ty-mainbox-container,
    .ty-sidebox,
    .ty-wysiwyg-content {
        background-color: var(--bw-background-color) !important;
    }
    {/if}

    {if $bw_accent_color|default:''}
    a,
    .ty-link {
        color: var(--bw-accent-color) !important;
    }

    .ty-menu__item-link,
    .ty-menu__item:hover .ty-menu__item-link,
    .ty-menu__item.active .ty-menu__item-link,
    .ty-menu__item-active .ty-menu__item-link,
    .ty-account-info__title a,
    .ty-breadcrumbs__a {
        color: var(--bw-accent-color) !important;
    }

    .ty-btn__primary,
    .ty-btn__primary:hover,
    .ty-btn__primary:focus {
        background: var(--bw-accent-color) !important;
        border-color: var(--bw-accent-color) !important;
    }

    .ty-btn__primary span {
        color: #fff !important;
    }

    .ty-tabs__item.active,
    .ty-tabs__item:hover {
        border-bottom-color: var(--bw-accent-color) !important;
    }
    {/if}
</style>
{/if}
