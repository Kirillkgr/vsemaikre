{assign var="data" value=$bw_buy_data|default:[]}

<div class="ty-wysiwyg-content">
    <h1>Купить магазин</h1>

    <form action="{fn_url('brending_wizart.buy_save')}" method="post" name="bw_buy_form">
        <h3>Данные витрины</h3>

        <div class="ty-control-group">
            <label class="ty-control-group__title" for="bw_vendor_nick">Ник (латиницей) для субдомена</label>
            <input id="bw_vendor_nick" class="ty-input-text" type="text" name="storefront[vendor_nick]" value="{$data.storefront.vendor_nick|default:''|escape}" placeholder="nick" required />
            <p class="ty-strong" style="margin-top: 6px; font-weight: normal;">Будет создана витрина вида: <strong>nick.llocalhost</strong></p>
        </div>

        <div class="ty-control-group">
            <label class="ty-control-group__title" for="bw_storefront_name">Название витрины</label>
            <input id="bw_storefront_name" class="ty-input-text" type="text" name="storefront[name]" value="{$data.storefront.name|default:''|escape}" required />
        </div>

        <h3>Данные пользователя</h3>

        <div class="ty-control-group">
            <label class="ty-control-group__title" for="bw_email">Email</label>
            <input id="bw_email" class="ty-input-text" type="email" name="user[email]" value="{$data.user.email|default:''|escape}" required />
        </div>

        <div class="ty-control-group">
            <label class="ty-control-group__title" for="bw_password1">Пароль</label>
            <input id="bw_password1" class="ty-input-text" type="password" name="user[password1]" value="" required />
        </div>

        <div class="ty-control-group">
            <label class="ty-control-group__title" for="bw_password2">Повторите пароль</label>
            <input id="bw_password2" class="ty-input-text" type="password" name="user[password2]" value="" required />
        </div>

        <div class="ty-control-group">
            <label class="ty-control-group__title" for="bw_firstname">Имя</label>
            <input id="bw_firstname" class="ty-input-text" type="text" name="user[firstname]" value="{$data.user.firstname|default:''|escape}" required />
        </div>

        <div class="ty-control-group">
            <label class="ty-control-group__title" for="bw_lastname">Фамилия</label>
            <input id="bw_lastname" class="ty-input-text" type="text" name="user[lastname]" value="{$data.user.lastname|default:''|escape}" required />
        </div>

        <div class="ty-control-group">
            <label class="ty-control-group__title" for="bw_phone">Телефон</label>
            <input id="bw_phone" class="ty-input-text" type="tel" name="user[phone]" value="{$data.user.phone|default:''|escape}" />
        </div>

        <div class="buttons-container">
            <button class="ty-btn__primary" type="submit">Отправить</button>
        </div>
    </form>
</div>
