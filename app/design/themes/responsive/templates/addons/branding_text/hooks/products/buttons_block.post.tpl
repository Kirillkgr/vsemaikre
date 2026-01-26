{if $details_page}
    {assign var="bt_pid" value=$product.product_id}

    <div class="ty-branding-text" id="bt-root-{$bt_pid}">
        <div class="ty-branding-text__actions" style="position:relative; z-index: 1000;">
            <button type="button" class="ty-btn ty-btn__primary" id="bt-open-{$bt_pid}">Брендирование</button>
            <button type="button" class="ty-btn" id="bt-close-{$bt_pid}" style="display:none;">Close</button>
        </div>

        <div class="ty-branding-text__panel" id="bt-panel-{$bt_pid}" style="display:none;">
            <div id="bt-status-{$bt_pid}"></div>

            <div class="ty-branding-text__stage" id="bt-stage-{$bt_pid}">
                <canvas id="bt-canvas-{$bt_pid}" width="520" height="520"></canvas>
            </div>

            <div class="ty-branding-text__tabs">
                <button type="button" class="ty-btn" data-bt-tab="text" data-bt-pid="{$bt_pid}">Text</button>
                <button type="button" class="ty-btn" data-bt-tab="image" data-bt-pid="{$bt_pid}">Image</button>
                <button type="button" class="ty-btn" data-bt-tab="uploads" data-bt-pid="{$bt_pid}">Uploads</button>
            </div>

            <div data-bt-pane="text" data-bt-pid="{$bt_pid}">
                <div class="ty-control-group">
                    <label class="ty-control-group__label">Text</label>
                    <input type="text" class="ty-input-text" id="bt-text-{$bt_pid}" />
                </div>
                <div class="ty-control-group">
                    <label class="ty-control-group__label">Color</label>
                    <input type="color" id="bt-text-color-{$bt_pid}" value="#000000" />
                </div>
                <div class="ty-control-group">
                    <label class="ty-control-group__label">Opacity</label>
                    <input type="range" id="bt-text-opacity-{$bt_pid}" min="0" max="1" step="0.05" value="1" />
                </div>
                <div class="ty-control-group">
                    <label class="ty-control-group__label">Size</label>
                    <input type="range" id="bt-text-size-{$bt_pid}" min="10" max="120" step="1" value="32" />
                </div>
                <button type="button" class="ty-btn ty-btn__primary" id="bt-add-text-{$bt_pid}">Apply text</button>
            </div>

            <div data-bt-pane="image" data-bt-pid="{$bt_pid}" style="display:none;">
                <div class="ty-control-group">
                    <label class="ty-control-group__label">Upload</label>
                    <input type="file" id="bt-upload-{$bt_pid}" accept="image/*" />
                </div>

                <div class="ty-control-group">
                    <label class="ty-control-group__label">Opacity</label>
                    <input type="range" id="bt-img-opacity-{$bt_pid}" min="0" max="1" step="0.05" value="1" />
                </div>

                <div class="ty-control-group">
                    <label class="ty-control-group__label">Presets</label>
                    <div>
                        <button type="button" class="ty-btn" data-bt-filter="none" data-bt-pid="{$bt_pid}">None</button>
                        <button type="button" class="ty-btn" data-bt-filter="bw" data-bt-pid="{$bt_pid}">B/W</button>
                        <button type="button" class="ty-btn" data-bt-filter="sepia" data-bt-pid="{$bt_pid}">Sepia</button>
                        <button type="button" class="ty-btn" data-bt-filter="vintage" data-bt-pid="{$bt_pid}">Vintage</button>
                        <button type="button" class="ty-btn" data-bt-filter="vivid" data-bt-pid="{$bt_pid}">Vivid</button>
                        <button type="button" class="ty-btn" data-bt-filter="warm" data-bt-pid="{$bt_pid}">Warm</button>
                        <button type="button" class="ty-btn" data-bt-filter="cool" data-bt-pid="{$bt_pid}">Cool</button>
                    </div>
                </div>

                <div class="ty-control-group">
                    <label class="ty-control-group__label">Use upload when saving</label>
                    <input type="checkbox" id="bt-use-upload-{$bt_pid}" checked="checked" />
                </div>

                <button type="button" class="ty-btn" id="bt-img-reset-{$bt_pid}">Reset</button>
            </div>

            <div data-bt-pane="uploads" data-bt-pid="{$bt_pid}" style="display:none;">
                <div id="bt-uploads-list-{$bt_pid}"></div>
            </div>

            <div class="ty-branding-text__footer">
                <button type="button" class="ty-btn ty-btn__primary" id="bt-save-{$bt_pid}">Save</button>
            </div>
        </div>

        <script type="text/javascript">
            (function (_, $) {
{literal}
                window.__BT__ = window.__BT__ || {};
                window.__BT__['{/literal}{$bt_pid}{literal}'] = {
                    bgUrl: '{/literal}{$product.main_pair.detailed.image_path|escape:"javascript" nofilter}{literal}',
                    urls: {
                        save: '{/literal}{"branding_text.save"|fn_url}{literal}',
                        load: '{/literal}{"branding_text.load"|fn_url}{literal}',
                        upload_logo: '{/literal}{"branding_text.upload_logo"|fn_url}{literal}',
                        list_uploads: '{/literal}{"branding_text.list_uploads"|fn_url}{literal}',
                        upload_preview: '{/literal}{"branding_text.upload_preview"|fn_url}{literal}',

                        uploadLogo: '{/literal}{"branding_text.upload_logo"|fn_url}{literal}',
                        listUploads: '{/literal}{"branding_text.list_uploads"|fn_url}{literal}',
                        uploadPreview: '{/literal}{"branding_text.upload_preview"|fn_url}{literal}'
                    }
                };
{/literal}
            }(Tygh, Tygh.$));
        </script>
    </div>
{/if}
