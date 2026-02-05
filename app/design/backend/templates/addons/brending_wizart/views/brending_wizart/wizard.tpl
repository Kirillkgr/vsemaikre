{assign var="step" value=$bw_step|default:1}
{assign var="data" value=$bw_data|default:[]}

<div class="mainbox">
    <h1>Мастер настройки витрины</h1>

    <div style="margin: 12px 0; padding: 10px; border: 1px solid #e5e5e5; border-radius: 6px;">
        Шаг {$step} из 4
    </div>

    {if $step == 1}
        <p>За несколько шагов мы соберём базовую информацию о вашем магазине.</p>

        <form action="{fn_url('brending_wizart.save?step=1')}" method="post" name="bw_step1_form">
            <div class="control-group">
                <label class="control-label" for="bw_vendor_nick">Ник (латиницей) для субдомена</label>
                <div class="controls">
                    <input id="bw_vendor_nick" class="input-large" type="text" name="wizard[vendor_nick]" value="{$data.vendor_nick|default:''|escape}" placeholder="nick" required />
                    <p class="muted" style="margin-top: 6px;">Будет создана витрина вида: <strong>nick.llocalhost</strong></p>
                </div>
            </div>

            <div class="control-group">
                <label class="control-label" for="bw_store_name">Название магазина</label>
                <div class="controls">
                    <input id="bw_store_name" class="input-large" type="text" name="wizard[store_name]" value="{$data.store_name|default:''|escape}" required />
                </div>
            </div>

            <div class="control-group">
                <label class="control-label" for="bw_store_description">Краткое описание</label>
                <div class="controls">
                    <textarea id="bw_store_description" class="input-large" name="wizard[store_description]" rows="4" maxlength="500" required>{$data.store_description|default:''|escape}</textarea>
                </div>
            </div>

            <div class="buttons-container">
                <button class="btn btn-primary" type="submit">Продолжить</button>
            </div>
        </form>
    {elseif $step == 2}
        <p>Выберите несколько общих товаров для старта (пока демо-список).</p>

        <form action="{fn_url('brending_wizart.save?step=2')}" method="post" name="bw_step2_form">
            <div style="margin: 10px 0;">
                <label><input type="checkbox" name="wizard[products][]" value="p1" {if $data.products && in_array('p1', $data.products)}checked{/if} /> Товар 1</label><br />
                <label><input type="checkbox" name="wizard[products][]" value="p2" {if $data.products && in_array('p2', $data.products)}checked{/if} /> Товар 2</label><br />
                <label><input type="checkbox" name="wizard[products][]" value="p3" {if $data.products && in_array('p3', $data.products)}checked{/if} /> Товар 3</label>
            </div>

            <div class="buttons-container">
                <a class="btn" href="{fn_url('brending_wizart.wizard?step=1')}">Назад</a>
                <button class="btn btn-primary" type="submit">Продолжить</button>
            </div>
        </form>
    {elseif $step == 3}
        <p>Выберите пресет оформления (демо).</p>

        <form action="{fn_url('brending_wizart.save?step=3')}" method="post" name="bw_step3_form">
            <div style="display: flex; gap: 12px; margin: 12px 0;">
                <label style="border: 1px solid #e5e5e5; border-radius: 8px; padding: 10px; flex: 1;">
                    <input type="radio" name="wizard[preset]" value="light" {if ($data.preset|default:'light')=='light'}checked{/if} />
                    <div style="margin-top: 8px;"><strong>Светлый</strong></div>
                    <div style="margin-top: 6px; height: 44px; background: #f6f7f8; border-radius: 6px;"></div>
                </label>
                <label style="border: 1px solid #e5e5e5; border-radius: 8px; padding: 10px; flex: 1;">
                    <input type="radio" name="wizard[preset]" value="dark" {if ($data.preset|default:'light')=='dark'}checked{/if} />
                    <div style="margin-top: 8px;"><strong>Тёмный</strong></div>
                    <div style="margin-top: 6px; height: 44px; background: #2b2f33; border-radius: 6px;"></div>
                </label>
                <label style="border: 1px solid #e5e5e5; border-radius: 8px; padding: 10px; flex: 1;">
                    <input type="radio" name="wizard[preset]" value="bright" {if ($data.preset|default:'light')=='bright'}checked{/if} />
                    <div style="margin-top: 8px;"><strong>Яркий</strong></div>
                    <div style="margin-top: 6px; height: 44px; background: linear-gradient(90deg, #ff3b30, #ffcc00, #34c759); border-radius: 6px;"></div>
                </label>
            </div>

            <div class="buttons-container">
                <a class="btn" href="{fn_url('brending_wizart.wizard?step=2')}">Назад</a>
                <button class="btn btn-primary" type="submit">Продолжить</button>
            </div>
        </form>
    {else}
        <h3>Готово!</h3>
        <p><strong>Название:</strong> {$data.store_name|default:'—'|escape}</p>
        <p><strong>Slug:</strong> {$data.store_slug|default:'—'|escape}</p>
        <p><strong>Товаров выбрано:</strong> {if $data.products}{count($data.products)}{else}0{/if}</p>
        <p><strong>Пресет:</strong> {$data.preset|default:'light'|escape}</p>

        <form action="{fn_url('brending_wizart.save?step=4')}" method="post" name="bw_finish_form">
            <div class="buttons-container">
                <a class="btn" href="{fn_url('brending_wizart.wizard?step=3')}">Назад</a>
                <button class="btn btn-primary" type="submit">Завершить настройку и открыть магазин</button>
            </div>
        </form>
    {/if}
</div>
