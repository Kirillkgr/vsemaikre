{assign var="step" value=$bw_step|default:1}
{assign var="data" value=$bw_data|default:[]}

<div class="ty-wysiwyg-content">
    <h1>Мастер настройки витрины</h1>

    <div style="margin: 12px 0; padding: 10px; border: 1px solid #e5e5e5; border-radius: 6px;">
        Шаг {$step} из 3
    </div>

    {if $step == 1}
        <p>Заполните базовые данные. Это нужно только один раз — дальше всё создадим автоматически.</p>

        <form action="{fn_url('brending_wizart.save?step=1')}" method="post" name="bw_step1_form">
            <h3>Данные витрины</h3>

            <div class="ty-control-group">
                <label class="ty-control-group__title" for="bw_vendor_nick">Ник (латиницей) для субдомена</label>
                <input id="bw_vendor_nick" class="ty-input-text" type="text" name="wizard[vendor_nick]" value="{$data.storefront.vendor_nick|default:''|escape}" placeholder="nick" required />
            </div>

            <div class="ty-control-group">
                <label class="ty-control-group__title" for="bw_storefront_name">Название витрины</label>
                <input id="bw_storefront_name" class="ty-input-text" type="text" name="wizard[storefront_name]" value="{$data.storefront.name|default:''|escape}" required />
            </div>

            <div class="ty-control-group">
                <label class="ty-control-group__title" for="bw_role">Кто вы?</label>
                <select id="bw_role" class="ty-input-text" name="wizard[role]">
                    <option value="streamer" {if ($data.role|default:'streamer')=='streamer'}selected{/if}>Стример</option>
                    <option value="blogger" {if ($data.role|default:'streamer')=='blogger'}selected{/if}>Блогер</option>
                </select>
            </div>

            <h3>Данные пользователя</h3>

            <div class="ty-control-group">
                <label class="ty-control-group__title" for="bw_email">Email</label>
                <input id="bw_email" class="ty-input-text" type="email" name="wizard[email]" value="{$data.user.email|default:''|escape}" required />
            </div>

            <div class="ty-control-group">
                <label class="ty-control-group__title" for="bw_password1">Пароль</label>
                <input id="bw_password1" class="ty-input-text" type="password" name="wizard[password1]" value="" required />
            </div>

            <div class="ty-control-group">
                <label class="ty-control-group__title" for="bw_password2">Повторите пароль</label>
                <input id="bw_password2" class="ty-input-text" type="password" name="wizard[password2]" value="" required />
            </div>

            <div class="ty-control-group">
                <label class="ty-control-group__title" for="bw_firstname">Имя</label>
                <input id="bw_firstname" class="ty-input-text" type="text" name="wizard[firstname]" value="{$data.user.firstname|default:''|escape}" required />
            </div>

            <div class="ty-control-group">
                <label class="ty-control-group__title" for="bw_lastname">Фамилия</label>
                <input id="bw_lastname" class="ty-input-text" type="text" name="wizard[lastname]" value="{$data.user.lastname|default:''|escape}" required />
            </div>

            <div class="ty-control-group">
                <label class="ty-control-group__title" for="bw_phone">Телефон</label>
                <input id="bw_phone" class="ty-input-text" type="tel" name="wizard[phone]" value="{$data.user.phone|default:''|escape}" />
            </div>

            <div class="buttons-container">
                <button class="ty-btn__primary" type="submit">Продолжить</button>
            </div>
        </form>
    {elseif $step == 2}
        <p>Выберите пресет оформления (демо).</p>

        <form action="{fn_url('brending_wizart.save?step=2')}" method="post" name="bw_step2_form">
            <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 12px 0;">
                <label style="border: 1px solid #e5e5e5; border-radius: 8px; padding: 10px;">
                    <input type="radio" name="wizard[preset]" value="light" {if ($data.preset|default:'light')=='light'}checked{/if} />
                    <div style="margin-top: 8px;"><strong>Светлый</strong></div>
                    <div style="margin-top: 6px; height: 44px; background: #f6f7f8; border-radius: 6px;"></div>
                </label>
                <label style="border: 1px solid #e5e5e5; border-radius: 8px; padding: 10px;">
                    <input type="radio" name="wizard[preset]" value="dark" {if ($data.preset|default:'light')=='dark'}checked{/if} />
                    <div style="margin-top: 8px;"><strong>Тёмный</strong></div>
                    <div style="margin-top: 6px; height: 44px; background: #2b2f33; border-radius: 6px;"></div>
                </label>
                <label style="border: 1px solid #e5e5e5; border-radius: 8px; padding: 10px;">
                    <input type="radio" name="wizard[preset]" value="bright" {if ($data.preset|default:'light')=='bright'}checked{/if} />
                    <div style="margin-top: 8px;"><strong>Яркий</strong></div>
                    <div style="margin-top: 6px; height: 44px; background: linear-gradient(90deg, #ff3b30, #ffcc00, #34c759); border-radius: 6px;"></div>
                </label>
            </div>

            <div class="ty-control-group">
                <label class="ty-control-group__title" for="bw_background_color">Цвет фона</label>
                <input id="bw_background_color" class="ty-input-text" type="color" name="wizard[background_color]" value="{$data.background_color|default:'#ffffff'|escape}" />
            </div>

            <div class="ty-control-group">
                <label class="ty-control-group__title" for="bw_accent_color">Цвет категорий/акцентов</label>
                <input id="bw_accent_color" class="ty-input-text" type="color" name="wizard[accent_color]" value="{$data.accent_color|default:'#ff3b30'|escape}" />
            </div>

            <div class="ty-control-group">
                <label class="ty-control-group__title" for="bw_text_color">Цвет текста</label>
                <input id="bw_text_color" class="ty-input-text" type="color" name="wizard[text_color]" value="{$data.text_color|default:'#111111'|escape}" />
            </div>

            <div data-bw-preview="box" style="margin: 12px 0; padding: 12px; border-radius: 8px; border: 1px solid #e5e5e5; background: {$data.background_color|default:'#ffffff'|escape}; color: {$data.text_color|default:'#111111'|escape};">
                <div style="font-weight: bold;">Предпросмотр</div>
                <div style="margin-top: 8px;">
                    <span data-bw-preview="btn" style="display: inline-block; padding: 6px 10px; border-radius: 6px; background: {$data.accent_color|default:'#ff3b30'|escape}; color: #fff;">Кнопка</span>
                    <span data-bw-preview="link" style="margin-left: 10px; color: {$data.accent_color|default:'#ff3b30'|escape}; text-decoration: underline;">Ссылка</span>
                </div>
            </div>

            <script>
            (function () {
                var bg = document.getElementById('bw_background_color');
                var accent = document.getElementById('bw_accent_color');
                var text = document.getElementById('bw_text_color');

                var previewBox = document.querySelector('[data-bw-preview="box"]');
                var previewBtn = document.querySelector('[data-bw-preview="btn"]');
                var previewLink = document.querySelector('[data-bw-preview="link"]');

                function apply() {
                    var bgVal = bg ? bg.value : '';
                    var accentVal = accent ? accent.value : '';
                    var textVal = text ? text.value : '';

                    if (previewBox) {
                        if (bgVal) previewBox.style.background = bgVal;
                        if (textVal) previewBox.style.color = textVal;
                    }
                    if (previewBtn && accentVal) {
                        previewBtn.style.background = accentVal;
                        previewBtn.style.borderColor = accentVal;
                    }
                    if (previewLink && accentVal) {
                        previewLink.style.color = accentVal;
                    }

                    if (bgVal) document.documentElement.style.setProperty('--bw-background-color', bgVal);
                    if (accentVal) document.documentElement.style.setProperty('--bw-accent-color', accentVal);
                    if (textVal) document.documentElement.style.setProperty('--bw-text-color', textVal);
                }

                if (bg) bg.addEventListener('input', apply);
                if (accent) accent.addEventListener('input', apply);
                if (text) text.addEventListener('input', apply);
                apply();
            })();
            </script>

            <div class="buttons-container">
                <a class="ty-btn" href="{fn_url('brending_wizart.wizard?step=1')}">Назад</a>
                <button class="ty-btn__primary" type="submit">Продолжить</button>
            </div>
        </form>
    {else}
        <h3>Проверьте данные</h3>
        <p><strong>ФИО:</strong> {$data.user.firstname|default:'—'|escape} {$data.user.lastname|default:''|escape}</p>
        <p><strong>Email:</strong> {$data.user.email|default:'—'|escape}</p>
        <p><strong>Ник:</strong> {$data.storefront.vendor_nick|default:'—'|escape}</p>
        <p><strong>Роль:</strong> {$data.role|default:'streamer'|escape}</p>
        <p><strong>Тема:</strong> {$data.preset|default:'light'|escape}</p>
        <p><strong>Фон:</strong> {$data.background_color|default:'—'|escape}</p>
        <p><strong>Акцент:</strong> {$data.accent_color|default:'—'|escape}</p>
        <p><strong>Текст:</strong> {$data.text_color|default:'—'|escape}</p>

        <form action="{fn_url('brending_wizart.buy_save')}" method="post" name="bw_finish_form" enctype="multipart/form-data">
            <h3>Логотип</h3>
            <div style="margin: 10px 0;">
                <div class="ty-control-group">
                    <label class="ty-control-group__title" for="bw_logo_header">Логотип для шапки витрины</label>
                    <input id="bw_logo_header" class="ty-input-text" type="file" name="bw_logo_header" accept="image/*" />
                </div>

                <div class="ty-control-group">
                    <label class="ty-control-group__title" for="bw_logo_list">Логотип для списка продавцов</label>
                    <input id="bw_logo_list" class="ty-input-text" type="file" name="bw_logo_list" accept="image/*" />
                </div>
            </div>

            <div class="buttons-container">
                <a class="ty-btn" href="{fn_url('brending_wizart.wizard?step=2')}">Назад</a>
                <button class="ty-btn__primary" type="submit">Завершить настройку и открыть магазин</button>
            </div>
        </form>
    {/if}
</div>
