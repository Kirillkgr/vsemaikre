{assign var="data" value=$bw_data|default:[]}

<div class="mainbox">
    <h1>Мой магазин</h1>

    <div class="row-fluid" style="display: flex; gap: 16px;">
        <div class="span6" style="flex: 1;">
            <h3 style="margin-top: 0;">Предпросмотр витрины</h3>

            {if $bw_storefront_url}
                <div style="margin: 8px 0 12px 0;">
                    <a class="btn" href="{$bw_storefront_url|escape}" target="_blank">Открыть витрину в новой вкладке</a>
                </div>
                <iframe
                    src="{$bw_storefront_url|escape}"
                    style="width: 100%; height: 720px; border: 1px solid #e5e5e5; border-radius: 8px; background: #fff;"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                ></iframe>
            {else}
                <div class="alert alert-warning">Витрина для текущего продавца не найдена. Сначала создайте витрину через мастер.</div>
            {/if}
        </div>

        <div class="span6" style="flex: 1;">
            <h3 style="margin-top: 0;">Настройки оформления</h3>

            <form action="{fn_url('brending_wizart.save_my_store')}" method="post" enctype="multipart/form-data" name="bw_my_store_form">
                <input type="hidden" name="security_hash" value="{$security_hash|default:''|escape}" />
                <div class="control-group">
                    <label class="control-label" for="bw_bg">Цвет фона</label>
                    <div class="controls">
                        <input id="bw_bg" type="color" name="wizard[background_color]" value="{$data.background_color|default:'#ffffff'|escape}" />
                        <input class="input-large" type="text" name="wizard[background_color_text]" value="{$data.background_color|default:''|escape}" placeholder="#ffffff" />
                    </div>
                </div>

                <div class="control-group">
                    <label class="control-label" for="bw_accent">Цвет акцента</label>
                    <div class="controls">
                        <input id="bw_accent" type="color" name="wizard[accent_color]" value="{$data.accent_color|default:'#ff3b30'|escape}" />
                        <input class="input-large" type="text" name="wizard[accent_color_text]" value="{$data.accent_color|default:''|escape}" placeholder="#ff3b30" />
                    </div>
                </div>

                <div class="control-group">
                    <label class="control-label" for="bw_text">Цвет текста</label>
                    <div class="controls">
                        <input id="bw_text" type="color" name="wizard[text_color]" value="{$data.text_color|default:'#111111'|escape}" />
                        <input class="input-large" type="text" name="wizard[text_color_text]" value="{$data.text_color|default:''|escape}" placeholder="#111111" />
                    </div>
                </div>

                <div class="control-group">
                    <label class="control-label" for="bw_logo_header">Логотип для шапки витрины</label>
                    <div class="controls">
                        <input id="bw_logo_header" type="file" name="bw_logo_header" accept="image/*" />
                        <p class="muted" style="margin: 6px 0 0 0;">Изображение будет автоматически приведено к нужному размеру (320×120).</p>
                    </div>
                </div>

                <div class="control-group">
                    <label class="control-label" for="bw_logo_list">Логотип для списка продавцов</label>
                    <div class="controls">
                        <input id="bw_logo_list" type="file" name="bw_logo_list" accept="image/*" />
                        <p class="muted" style="margin: 6px 0 0 0;">Изображение будет автоматически приведено к нужному размеру (200×200).</p>
                    </div>
                </div>

                <div class="buttons-container">
                    <button class="btn btn-primary" type="submit">Сохранить</button>
                </div>
            </form>

            <script>
            (function () {
                function normalizeHex(value) {
                    value = (value || '').trim();
                    if (value === '') {
                        return '';
                    }
                    if (value[0] !== '#') {
                        value = '#' + value;
                    }
                    return value;
                }

                function bind(colorSelector, textSelector) {
                    var color = document.querySelector(colorSelector);
                    var text = document.querySelector(textSelector);
                    if (!color || !text) {
                        return;
                    }

                    color.addEventListener('input', function () {
                        text.value = color.value;
                    });

                    text.addEventListener('input', function () {
                        var v = normalizeHex(text.value);
                        if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(v)) {
                            color.value = v;
                        }
                    });
                }

                bind('#bw_bg', 'input[name="wizard[background_color_text]"]');
                bind('#bw_accent', 'input[name="wizard[accent_color_text]"]');
                bind('#bw_text', 'input[name="wizard[text_color_text]"]');
            })();
            </script>
        </div>
    </div>
</div>
