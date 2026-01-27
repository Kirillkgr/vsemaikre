(function (window, document) {
  'use strict';

  var DEBUG = !!window.__BT_DEBUG__;
  window.__BT_LOGS__ = window.__BT_LOGS__ || [];
  window.__BT_INIT__ = window.__BT_INIT__ || {};

  function dbg(event, payload) {
    if (!DEBUG) return;
    try {
      var msg = {
        ts: Date.now(),
        event: String(event || ''),
        payload: payload
      };
      window.__BT_LOGS__.push(msg);
      if (window.console && window.console.log) {
        window.console.log('[branding_text]', msg.event, msg.payload || '');
      }
    } catch (e) {}
  }

  dbg('designer_js_loaded', { readyState: document.readyState });

  function btGetGlobalCfg() {
    try {
      return window.__BT_GLOBAL__ || null;
    } catch (e) {
      return null;
    }
  }

  function btBuildUrlWithParams(url, params) {
    var sep = (url.indexOf('?') >= 0) ? '&' : '?';
    var s = [];
    for (var k in (params || {})) {
      if (!params.hasOwnProperty(k)) continue;
      s.push(encodeURIComponent(k) + '=' + encodeURIComponent(String(params[k])));
    }
    return url + (s.length ? (sep + s.join('&')) : '');
  }

  function btParseProductIdFromHref(href) {
    if (!href) return 0;
    try {
      var m = String(href).match(/[?&]product_id=(\d+)/);
      if (m && m[1]) return parseInt(m[1], 10) || 0;
    } catch (e) {}
    return 0;
  }

  function btApplyPreviewToListingImage(linkEl, imgEl) {
    var g = btGetGlobalCfg();
    if (!g || !g.previewForProduct || !g.user_id) return;
    if (!linkEl || !imgEl) return;
    if (imgEl.getAttribute && imgEl.getAttribute('data-bt-preview-applied') === 'Y') return;

    var pid = btParseProductIdFromHref(linkEl.getAttribute ? linkEl.getAttribute('href') : '');
    if (!pid) return;

    function extractThumbSizeFromSrc(src) {
      if (!src) return null;
      try {
        // CS-Cart thumbnails: /images/thumbnails/W/H/detailed/...
        var m = String(src).match(/\/thumbnails\/(\d+)\/(\d+)\//);
        if (m && m[1] && m[2]) {
          return { w: parseInt(m[1], 10) || 0, h: parseInt(m[2], 10) || 0 };
        }
      } catch (e) {}
      return null;
    }

    function extractRenderedSize(img) {
      try {
        var r = img.getBoundingClientRect ? img.getBoundingClientRect() : null;
        if (r && r.width && r.height) {
          var w = Math.round(r.width);
          var h = Math.round(r.height);
          if (w > 0 && h > 0 && w <= 2000 && h <= 2000) return { w: w, h: h };
        }
      } catch (e) {}
      return null;
    }

    var origSrc = imgEl.getAttribute('src') || '';
    var size = extractThumbSizeFromSrc(origSrc) || extractRenderedSize(imgEl);
    var params = { product_id: pid, _t: 0 };
    if (size && size.w && size.h) {
      params.w = size.w;
      params.h = size.h;
    }
    var url = btBuildUrlWithParams(g.previewForProduct, params);

    // Keep original src for fallback.
    origSrc = imgEl.getAttribute('data-bt-orig-src') || imgEl.src;
    imgEl.setAttribute('data-bt-orig-src', origSrc);

    // Avoid console 404 spam: precheck by HEAD and only swap if preview exists.
    window.__BT_PREVIEW_HEAD__ = window.__BT_PREVIEW_HEAD__ || {};
    var sw = (size && size.w) ? size.w : 0;
    var sh = (size && size.h) ? size.h : 0;
    var key = String(pid) + '|' + String(sw) + 'x' + String(sh);
    if (!window.__BT_PREVIEW_HEAD__[key]) {
      window.__BT_PREVIEW_HEAD__[key] = fetch(url, { method: 'HEAD', credentials: 'same-origin' })
          .then(function (r) { return !!(r && r.ok); })
          .catch(function () { return false; });
    }

    window.__BT_PREVIEW_HEAD__[key].then(function (ok) {
      if (!ok) return;
      imgEl.src = url;
      imgEl.onerror = function () {
        imgEl.src = origSrc;
      };
    });
  }

  function btReplaceCatalogImages(context) {
    var g = btGetGlobalCfg();
    if (!g || !g.previewForProduct || !g.user_id) return;
    context = context || document;

    // Grid/listing product cards: <a href="...products.view&product_id=..."> <img class="ty-pict cm-image" ...>
    var links = context.querySelectorAll ? context.querySelectorAll('a[href*="dispatch=products.view"][href*="product_id="], a[href*="products.view"][href*="product_id="]') : [];
    for (var i = 0; i < links.length; i++) {
      var a = links[i];
      var img = a.querySelector ? a.querySelector('img.cm-image, img.ty-pict, img') : null;
      if (!img) continue;
      btApplyPreviewToListingImage(a, img);
    }
  }

  function setStatus(el, text, isError) {
    if (!el) return;
    el.innerHTML = '<div class="' + (isError ? 'ty-error-text' : 'ty-success-text') + '">' + text + '</div>';
  }

  function ensureFabric(cb, onError) {
    if (window.fabric) return cb();

    // Load Fabric only once per page to avoid duplicate definitions.
    // Use a shared promise and a stable script id.
    window.__BT_FABRIC__ = window.__BT_FABRIC__ || {};
    if (!window.__BT_FABRIC__.promise) {
      window.__BT_FABRIC__.promise = new Promise(function (resolve, reject) {
        try {
          if (window.fabric) {
            resolve(window.fabric);
            return;
          }

          var existing = document.getElementById('bt-fabric-script');
          if (existing) {
            existing.addEventListener('load', function () { resolve(window.fabric); });
            existing.addEventListener('error', function () {
              reject(new Error('Failed to load fabric.js'));
            });
            return;
          }

          function buildUrl(rel) {
            try {
              if (window.Tygh && window.Tygh.fn_url) {
                return window.Tygh.fn_url(rel);
              }
            } catch (e0) {}
            return rel;
          }

          var localSrc = buildUrl('js/addons/branding_text/vendor/fabric.min.js');
          var cdnSrc = 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js';

          function attachHandlers(scriptEl, errMessageIfNoFabric) {
            scriptEl.onload = function () {
              if (window.fabric) {
                resolve(window.fabric);
              } else {
                reject(new Error(errMessageIfNoFabric || 'fabric.js loaded but window.fabric is missing'));
              }
            };
          }

          var s = document.createElement('script');
          s.id = 'bt-fabric-script';
          s.async = true;
          s.src = localSrc;
          attachHandlers(s);

          s.onerror = function () {
            // Fallback to CDN if local file is missing. Create a fresh tag (some browsers don't reliably re-load on src change after error).
            try { if (s && s.parentNode) s.parentNode.removeChild(s); } catch (e0) {}

            var s2 = document.createElement('script');
            s2.id = 'bt-fabric-script';
            s2.async = true;
            s2.src = cdnSrc;
            attachHandlers(s2, 'fabric.js loaded from CDN but window.fabric is missing');

            s2.onerror = function () {
              reject(new Error('Failed to load fabric.js (local file missing and CDN may be blocked by browser privacy settings)'));
            };

            document.head.appendChild(s2);
          };

          document.head.appendChild(s);
        } catch (e) {
          reject(e);
        }
      });
    }

    window.__BT_FABRIC__.promise
        .then(function () { try { cb(); } catch (e0) {} })
        .catch(function (e) { try { (onError || function () {})(e); } catch (e1) {} });
  }

  function initForProduct(pid) {
    if (window.__BT_INIT__[pid]) {
      dbg('init_skip_already_initialized', { pid: pid });
      return;
    }

    var cfg = window.__BT__ && window.__BT__[pid] ? window.__BT__[pid] : null;
    if (!cfg) return;

    window.__BT_INIT__[pid] = true;
    dbg('init_for_product', { pid: pid, cfgKeys: Object.keys(cfg || {}) });

    var btnOpen = document.getElementById('bt-open-' + pid);
    var panel = document.getElementById('bt-panel-' + pid);
    var btnClose = document.getElementById('bt-close-' + pid);
    var stage = document.getElementById('bt-stage-' + pid);

    var statusEl = document.getElementById('bt-status-' + pid);

    var btnAddText = document.getElementById('bt-add-text-' + pid);
    var btnSave = document.getElementById('bt-save-' + pid);
    var btnAddToCart = document.getElementById('bt-add-to-cart-' + pid);
    var upload = document.getElementById('bt-upload-' + pid);

    var PRINT = { x: 0, y: 0, w: 520, h: 520 };

    var state = {
      canvas: null,
      printRect: null,
      bgImage: null,
      clipImage: null,
      logoObject: null,
      lastLogoFile: null,
      textObject: null,
      origStageParent: null,
      origStageNext: null,
      galleryEl: null,
      origGalleryHtml: null,
      ro: null,
      hiddenRightNodes: null,
      activeUploadId: 0,
      logoFilterPreset: 'none',
      logoFiltersMode: 'preset'
    };

    function qs(id) { return document.getElementById(id + '-' + pid); }

    function normalizeUrls() {
      if (!cfg || !cfg.urls) return;
      // Support both camelCase and snake_case keys (templates may use either)
      if (!cfg.urls.listUploads && cfg.urls.list_uploads) cfg.urls.listUploads = cfg.urls.list_uploads;
      if (!cfg.urls.uploadPreview && cfg.urls.upload_preview) cfg.urls.uploadPreview = cfg.urls.upload_preview;
      if (!cfg.urls.uploadLogo && cfg.urls.upload_logo) cfg.urls.uploadLogo = cfg.urls.upload_logo;
      if (!cfg.urls.previewForProduct && cfg.urls.preview_for_product) cfg.urls.previewForProduct = cfg.urls.preview_for_product;
    }

    normalizeUrls();

    function applyPreviewToProductImage(opts) {
      opts = opts || {};
      if (!cfg || !cfg.urls || !cfg.urls.previewForProduct) return;
      if (!cfg.user_id) return; // replace only for authorized users

      // Build preview URL (owner context is derived from session/auth on server)
      var url = buildUrlWithParams(cfg.urls.previewForProduct, {
        product_id: pid,
        _t: opts.cacheBust ? Date.now() : 0
      });

      // Locate product image block
      var wrapper = document.querySelector('.ty-product-img.cm-preview-wrapper') || document.querySelector('.ty-product-img');
      if (!wrapper) return;

      // easyzoom structure: <span class="ty-image-zoom__wrapper ..."><a ...><img ... /></a></span>
      var link = wrapper.querySelector('a.cm-image-previewer, a.cm-previewer, a.ty-previewer, a#det_img_link_' + pid);
      if (!link) link = wrapper.querySelector('a[href]');
      var img = link ? link.querySelector('img.ty-pict, img.cm-image, img') : wrapper.querySelector('img.ty-pict, img.cm-image, img');
      if (!img) return;

      // Remember original to allow fallback
      if (!state._origProductImage) {
        state._origProductImage = {
          href: link ? link.getAttribute('href') : '',
          src: img.getAttribute('src')
        };
      }

      if (link) {
        link.setAttribute('href', url);
      }
      img.setAttribute('src', url);
      img.onerror = function () {
        try {
          img.onerror = null;
          if (state._origProductImage && state._origProductImage.src) {
            img.setAttribute('src', state._origProductImage.src);
          }
          if (link && state._origProductImage && state._origProductImage.href) {
            link.setAttribute('href', state._origProductImage.href);
          }
        } catch (e) {}
      };
    }

    function clampToPrint(obj) {
      if (!obj || !state.canvas) return;
      obj.setCoords();
      var b = obj.getBoundingRect(true, true);
      var dx = 0;
      var dy = 0;
      if (b.left < 0) dx = 0 - b.left;
      if (b.top < 0) dy = 0 - b.top;
      if (b.left + b.width > state.canvas.getWidth()) dx = state.canvas.getWidth() - (b.left + b.width);
      if (b.top + b.height > state.canvas.getHeight()) dy = state.canvas.getHeight() - (b.top + b.height);
      if (b.top + b.height > PRINT.y + PRINT.h) dy = (PRINT.y + PRINT.h) - (b.top + b.height);
      if (dx || dy) {
        obj.left += dx;
        obj.top += dy;
        obj.setCoords();
      }
    }

    function initCanvas() {
      if (state.canvas) return;
      ensureFabric(function () {
        if (state.canvas) return;
        dbg('fabric_ready', { pid: pid });
        state.canvas = new window.fabric.Canvas('bt-canvas-' + pid, { preserveObjectStacking: true, selection: true });

        // Default print area = whole canvas
        PRINT.x = 0;
        PRINT.y = 0;
        PRINT.w = state.canvas.getWidth();
        PRINT.h = state.canvas.getHeight();

        if (cfg.bgUrl) {
          dbg('bg_load_start', { pid: pid, url: cfg.bgUrl });
          window.fabric.Image.fromURL(cfg.bgUrl, function (img) {
            try {
              var cw = state.canvas.getWidth();
              var ch = state.canvas.getHeight();
              var scale = Math.min(cw / img.width, ch / img.height);
              img.set({ originX: 'left', originY: 'top', selectable: false, evented: false });
              img.scaleX = scale;
              img.scaleY = scale;
              img.left = (cw - img.width * scale) / 2;
              img.top = (ch - img.height * scale) / 2;
              state.bgImage = img;
              state.canvas.setBackgroundImage(img, state.canvas.requestRenderAll.bind(state.canvas));
              dbg('bg_load_ok', { pid: pid, w: img.width, h: img.height });

              // Prepare clip image (alpha mask) using the same PNG.
              window.fabric.Image.fromURL(cfg.bgUrl, function (clipImg) {
                try {
                  var cscale = Math.min(cw / clipImg.width, ch / clipImg.height);
                  clipImg.set({ originX: 'left', originY: 'top', selectable: false, evented: false });
                  clipImg.scaleX = cscale;
                  clipImg.scaleY = cscale;
                  clipImg.left = (cw - clipImg.width * cscale) / 2;
                  clipImg.top = (ch - clipImg.height * cscale) / 2;
                  clipImg.absolutePositioned = true;
                  state.clipImage = clipImg;
                  dbg('clip_load_ok', { pid: pid, w: clipImg.width, h: clipImg.height });
                  if (state.logoObject) {
                    state.logoObject.set({ clipPath: state.clipImage });
                    state.canvas.requestRenderAll();
                  }
                  if (state.textObject) {
                    state.textObject.set({ clipPath: state.clipImage });
                    state.canvas.requestRenderAll();
                  }
                } catch (e2) {
                  dbg('clip_load_error', { pid: pid, message: (e2 && e2.message) ? e2.message : e2 });
                }
              }, { crossOrigin: 'anonymous' });
            } catch (e) {
              dbg('bg_load_error', { pid: pid, message: (e && e.message) ? e.message : e });
            }
          }, {
            crossOrigin: 'anonymous'
          });
        } else {
          dbg('bg_missing', { pid: pid });
        }

        state.printRect = new window.fabric.Rect({
          left: PRINT.x,
          top: PRINT.y,
          width: PRINT.w,
          height: PRINT.h,
          fill: 'rgba(0,0,0,0)',
          stroke: '#666',
          strokeWidth: 1,
          selectable: false,
          evented: false
        });
        state.canvas.add(state.printRect);
        state.canvas.on('object:moving', function (e) { clampToPrint(e.target); });
        state.canvas.on('object:scaling', function (e) { clampToPrint(e.target); });
        state.canvas.on('object:rotating', function (e) { clampToPrint(e.target); });
      }, function () {
        dbg('fabric_load_failed', { pid: pid });
        setStatus(statusEl, 'Не удалось загрузить библиотеку конструктора', true);
      });
    }

    function findRightColumnRoot() {
      // Try to hide everything in the product right column (price, socials, etc.)
      // keeping only our addon block.
      if (!panel) return null;
      if (!panel.closest) return null;

      var right = panel.closest('.ty-product-block__right');
      if (right) return right;

      // Fallbacks for themes
      right = panel.closest('.product-main-info__right');
      if (right) return right;

      right = panel.closest('.product-info__right');
      if (right) return right;

      // Heuristic: find nearest container that holds cart/social buttons near our addon
      var addonRoot = panel.closest ? panel.closest('.ty-branding-text') : null;
      if (addonRoot) {
        var cur = addonRoot;
        while (cur && cur.parentElement) {
          var hasSocial = cur.querySelector && cur.querySelector('.ty-social-buttons');
          var hasCart = cur.querySelector && cur.querySelector('.ty-btn__add-to-cart');
          if (hasSocial || hasCart) {
            return cur;
          }
          cur = cur.parentElement;
        }
      }

      return null;
    }

    function hideRightColumnExtras() {
      var root = findRightColumnRoot();
      if (!root) return;

      var addonRoot = panel.closest ? panel.closest('.ty-branding-text') : null;
      // Keep visible only the direct child of right column that contains our addon block.
      var keepEl = null;
      if (addonRoot) {
        var cur = addonRoot;
        while (cur && cur.parentElement && cur.parentElement !== root) {
          cur = cur.parentElement;
        }
        if (cur && cur.parentElement === root) {
          keepEl = cur;
        }
      }

      var nodes = [];
      for (var n = root.firstElementChild; n; n = n.nextElementSibling) {
        if (keepEl && n === keepEl) continue;
        nodes.push(n);
      }
      if (!nodes.length) return;

      state.hiddenRightNodes = [];
      for (var i = 0; i < nodes.length; i++) {
        var el = nodes[i];
        state.hiddenRightNodes.push({ el: el, display: el.style.display });
        el.style.display = 'none';
      }
      dbg('right_column_extras_hidden', { pid: pid, count: nodes.length });
    }

    function restoreRightColumnExtras() {
      if (!state.hiddenRightNodes) return;
      for (var i = 0; i < state.hiddenRightNodes.length; i++) {
        var it = state.hiddenRightNodes[i];
        try {
          it.el.style.display = it.display;
        } catch (e) {}
      }
      dbg('right_column_extras_restored', { pid: pid, count: state.hiddenRightNodes.length });
      state.hiddenRightNodes = null;
    }

    function resizeCanvasToStage() {
      if (!state.canvas || !stage) return;
      var canvasEl = document.getElementById('bt-canvas-' + pid);
      if (!canvasEl) return;
      var rect = stage.getBoundingClientRect();
      var w = Math.max(320, Math.round(rect.width || 520));
      w = Math.min(520, w);
      var h = w; // keep square to avoid CSS stretching issues
      if (w === state.canvas.getWidth() && h === state.canvas.getHeight()) return;

      dbg('canvas_resize', { pid: pid, w: w, h: h });

      stage.style.height = h + 'px';
      stage.style.width = w + 'px';

      // Update both internal and CSS sizes for Fabric (affects upper/lower canvas)
      state.canvas.setDimensions({ width: w, height: h }, { cssOnly: false });
      try {
        if (state.canvas.upperCanvasEl) {
          state.canvas.upperCanvasEl.style.width = w + 'px';
          state.canvas.upperCanvasEl.style.height = h + 'px';
        }
        if (state.canvas.lowerCanvasEl) {
          state.canvas.lowerCanvasEl.style.width = w + 'px';
          state.canvas.lowerCanvasEl.style.height = h + 'px';
        }
      } catch (e0) {}

      if (state.bgImage) {
        var bscale = Math.min(w / state.bgImage.width, h / state.bgImage.height);
        state.bgImage.scaleX = bscale;
        state.bgImage.scaleY = bscale;
        state.bgImage.left = (w - state.bgImage.width * bscale) / 2;
        state.bgImage.top = (h - state.bgImage.height * bscale) / 2;
        state.canvas.setBackgroundImage(state.bgImage, state.canvas.requestRenderAll.bind(state.canvas));
      }

      if (state.clipImage) {
        var cscale2 = Math.min(w / state.clipImage.width, h / state.clipImage.height);
        state.clipImage.scaleX = cscale2;
        state.clipImage.scaleY = cscale2;
        state.clipImage.left = (w - state.clipImage.width * cscale2) / 2;
        state.clipImage.top = (h - state.clipImage.height * cscale2) / 2;
      }

      // Update print area + its rect to match new canvas size
      PRINT.x = 0;
      PRINT.y = 0;
      PRINT.w = w;
      PRINT.h = h;
      if (state.printRect) {
        state.printRect.set({ left: 0, top: 0, width: w, height: h });
      }
      state.canvas.requestRenderAll();
    }

    function startStageObserver() {
      if (state.ro || !stage || !window.ResizeObserver) return;
      state.ro = new window.ResizeObserver(function () {
        try {
          resizeCanvasToStage();
        } catch (e) {}
      });
      state.ro.observe(stage);
      dbg('resize_observer_started', { pid: pid });
    }

    function stopStageObserver() {
      if (!state.ro) return;
      try { state.ro.disconnect(); } catch (e) {}
      state.ro = null;
      dbg('resize_observer_stopped', { pid: pid });
    }

    function mountStageIntoGallery() {
      if (!stage) {
        dbg('mount_no_stage', { pid: pid });
        return;
      }
      if (state.galleryEl) return;

      // Try to locate the product image preview block
      var gallery = document.querySelector('.ty-product-img.cm-preview-wrapper');
      if (!gallery) {
        dbg('mount_gallery_not_found', { pid: pid });
        return;
      }

      state.galleryEl = gallery;
      state.origStageParent = stage.parentNode;
      state.origStageNext = stage.nextSibling;
      state.origGalleryHtml = gallery.innerHTML;

      dbg('mount_stage', { pid: pid });

      // Apply the same centering behavior as product preview
      state._origGalleryTextAlign = gallery.style.textAlign;
      state._origStageDisplay = stage.style.display;
      state._origStageMargin = stage.style.margin;
      state._origStageMaxWidth = stage.style.maxWidth;
      state._origStageWidth = stage.style.width;
      state._origStageBoxSizing = stage.style.boxSizing;

      gallery.style.textAlign = 'center';
      stage.style.display = 'inline-block';
      stage.style.margin = '0 auto';
      stage.style.maxWidth = '100%';
      stage.style.width = '100%';
      stage.style.boxSizing = 'border-box';

      gallery.innerHTML = '';
      gallery.appendChild(stage);

      initCanvas();
      setTimeout(function () { resizeCanvasToStage(); }, 0);
      window.addEventListener('resize', resizeCanvasToStage);
      startStageObserver();
    }

    function unmountStageFromGallery() {
      if (!stage) return;
      if (!state.galleryEl) return;
      dbg('unmount_stage', { pid: pid });

      window.removeEventListener('resize', resizeCanvasToStage);
      stopStageObserver();

      // Restore gallery html
      try {
        state.galleryEl.innerHTML = state.origGalleryHtml || '';
      } catch (e) {}

      // Restore styles
      try {
        state.galleryEl.style.textAlign = state._origGalleryTextAlign || '';
      } catch (e1) {}
      try {
        stage.style.display = state._origStageDisplay || 'none';
        stage.style.margin = state._origStageMargin || '';
        stage.style.maxWidth = state._origStageMaxWidth || '';
        stage.style.width = state._origStageWidth || '';
        stage.style.boxSizing = state._origStageBoxSizing || '';
      } catch (e2) {}

      // Put stage back
      try {
        stage.style.display = 'none';
        if (state.origStageParent) {
          if (state.origStageNext && state.origStageNext.parentNode === state.origStageParent) {
            state.origStageParent.insertBefore(stage, state.origStageNext);
          } else {
            state.origStageParent.appendChild(stage);
          }
        }
      } catch (e) {}

      state.galleryEl = null;
      state.origGalleryHtml = null;
    }

    function showPanel(show) {
      if (!panel) return;
      if (show && panel.style.display === 'block') {
        dbg('panel_open_skip_already_open', { pid: pid });
        return;
      }

      panel.style.display = show ? 'block' : 'none';
      if (btnOpen) btnOpen.style.display = show ? 'none' : '';
      dbg(show ? 'panel_open' : 'panel_close', { pid: pid });
      if (show) {
        hideRightColumnExtras();
        mountStageIntoGallery();
        // Ensure canvas exists early and try to restore previously saved state
        initCanvas();
        loadState();
      } else {
        unmountStageFromGallery();
        restoreRightColumnExtras();
      }
    }

    function loadState() {
      if (!cfg || !cfg.urls || !cfg.urls.load) return;
      var url = buildUrlWithParams(cfg.urls.load, { product_id: pid });
      dbg('load_start', { pid: pid, url: url });
      fetchJson(url, { credentials: 'same-origin' })
          .then(function (json) {
            if (!json || !json.ok) {
              dbg('load_failed', { pid: pid, response: json });
              return;
            }

            if (!json.item) {
              dbg('load_empty', { pid: pid });
              return;
            }

            dbg('load_ok', { pid: pid, item_id: json.item.item_id });

            // Restore text
            try {
              if (json.item.text_value && qs('bt-text')) qs('bt-text').value = String(json.item.text_value);
              if (json.item.text_params && typeof json.item.text_params === 'object') {
                var tp = json.item.text_params;
                if (qs('bt-text-color') && tp.fill) qs('bt-text-color').value = String(tp.fill);
                if (qs('bt-text-opacity') && tp.opacity != null) qs('bt-text-opacity').value = String(tp.opacity);
                if (qs('bt-text-size') && tp.fontSize != null) qs('bt-text-size').value = String(tp.fontSize);
              }
            } catch (e0) {}

            // Apply text to canvas
            initCanvas();
            if (json.item.text_params && typeof json.item.text_params === 'object') {
              var tpp = json.item.text_params;
              function sanitizeOriginX(v) {
                v = String(v || '').toLowerCase();
                return (v === 'left' || v === 'center' || v === 'right') ? v : 'left';
              }
              function sanitizeOriginY(v) {
                v = String(v || '').toLowerCase();
                // IMPORTANT: do not allow invalid values like 'alphabetical' that can leak into canvas textBaseline.
                return (v === 'top' || v === 'center' || v === 'bottom') ? v : 'top';
              }

              if (!state.textObject && window.fabric) {
                state.textObject = new window.fabric.IText(String(json.item.text_value || ' '), {
                  left: (tpp.left != null) ? tpp.left : (PRINT.x + 20),
                  top: (tpp.top != null) ? tpp.top : (PRINT.y + 20),
                  fontFamily: tpp.fontFamily || 'Arial',
                  fontSize: (tpp.fontSize != null) ? tpp.fontSize : 32,
                  fill: tpp.fill || '#000000',
                  opacity: (tpp.opacity != null) ? tpp.opacity : 1,
                  angle: (tpp.angle != null) ? tpp.angle : 0,
                  scaleX: (tpp.scaleX != null) ? tpp.scaleX : 1,
                  scaleY: (tpp.scaleY != null) ? tpp.scaleY : 1,
                  originX: sanitizeOriginX(tpp.originX),
                  originY: sanitizeOriginY(tpp.originY)
                });
                state.canvas.add(state.textObject);
              } else if (state.textObject) {
                state.textObject.set({
                  text: String(json.item.text_value || ' '),
                  left: (tpp.left != null) ? tpp.left : state.textObject.left,
                  top: (tpp.top != null) ? tpp.top : state.textObject.top,
                  fontFamily: tpp.fontFamily || state.textObject.fontFamily,
                  fontSize: (tpp.fontSize != null) ? tpp.fontSize : state.textObject.fontSize,
                  fill: tpp.fill || state.textObject.fill,
                  opacity: (tpp.opacity != null) ? tpp.opacity : state.textObject.opacity,
                  angle: (tpp.angle != null) ? tpp.angle : state.textObject.angle,
                  scaleX: (tpp.scaleX != null) ? tpp.scaleX : state.textObject.scaleX,
                  scaleY: (tpp.scaleY != null) ? tpp.scaleY : state.textObject.scaleY,
                  originX: sanitizeOriginX(tpp.originX || state.textObject.originX),
                  originY: sanitizeOriginY(tpp.originY || state.textObject.originY)
                });
              }
              if (state.textObject) {
                state.textObject.setCoords();
                if (state.clipImage) {
                  state.textObject.set({ clipPath: state.clipImage });
                }
                clampToPrint(state.textObject);
              }
            } else if (json.item.text_value) {
              upsertText();
              if (state.textObject && state.clipImage) {
                state.textObject.set({ clipPath: state.clipImage });
              }
            }

            // IMPORTANT: Set pending logo params BEFORE setActiveUpload(), because setActiveUpload is async
            // and may load+apply immediately; otherwise we have a race and filters/params may not be restored.
            if (json.item.logo_params && typeof json.item.logo_params === 'object') {
              state._pendingLogoParams = json.item.logo_params;
              try {
                var lpp = json.item.logo_params;
                if (lpp && lpp.filters && typeof lpp.filters === 'object') {
                  if (lpp.filters.preset && typeof lpp.filters.preset === 'string') {
                    state.logoFilterPreset = String(lpp.filters.preset);
                  }
                  if (qs('bt-img-bright') && lpp.filters.brightness != null) qs('bt-img-bright').value = String(lpp.filters.brightness);
                  if (qs('bt-img-contrast') && lpp.filters.contrast != null) qs('bt-img-contrast').value = String(lpp.filters.contrast);
                  if (qs('bt-img-sat') && lpp.filters.saturation != null) qs('bt-img-sat').value = String(lpp.filters.saturation);
                }
              } catch (e1) {}
            }

            // Restore logo from saved upload id
            if (json.item.logo_upload_id) {
              state.activeUploadId = parseInt(json.item.logo_upload_id, 10) || 0;
              if (state.activeUploadId) {
                setActiveUpload(state.activeUploadId);
              }
            }

            // Enforce stacking: text must be above logo (and both below print rect)
            try {
              if (state.logoObject && state.logoObject.bringToFront) state.logoObject.bringToFront();
              if (state.textObject && state.textObject.bringToFront) state.textObject.bringToFront();
              if (state.printRect && state.printRect.bringToFront) state.printRect.bringToFront();
            } catch (e2) {}

            if (state.canvas) state.canvas.requestRenderAll();
          })
          .catch(function (e) {
            dbg('load_error', { pid: pid, message: (e && e.message) ? e.message : e });
            try {
              if (e && e._bt && e._bt.status) {
                setStatus(statusEl, 'Ошибка загрузки (' + e._bt.status + '): ' + (e._bt.contentType || '') + '<br />' + (e._bt.snippet || ''), true);
              } else {
                setStatus(statusEl, 'Ошибка загрузки: ' + (e && e.message ? e.message : e), true);
              }
            } catch (e0) {}
          });
    }

    function fetchJson(url, opts) {
      return fetch(url, opts || { credentials: 'same-origin' })
          .then(function (r) {
            return r.text().then(function (txt) {
              var ct = '';
              try { ct = r.headers && r.headers.get ? (r.headers.get('content-type') || '') : ''; } catch (e0) {}

              // Some CS-Cart errors return HTML; avoid JSON.parse crash and surface details.
              var json = null;
              try {
                json = txt ? JSON.parse(txt) : null;
              } catch (e1) {
                var err = new Error('Non-JSON response');
                err._bt = {
                  status: r.status,
                  ok: r.ok,
                  contentType: ct,
                  snippet: String(txt || '').slice(0, 350)
                };
                throw err;
              }

              // If HTTP is not ok, still throw with snippet (but keep parsed json for debug).
              if (!r.ok) {
                var err2 = new Error('HTTP ' + r.status);
                err2._bt = {
                  status: r.status,
                  ok: r.ok,
                  contentType: ct,
                  snippet: String(txt || '').slice(0, 350),
                  json: json
                };
                throw err2;
              }

              return json;
            });
          });
    }

    function buildUrlWithParams(url, params) {
      var sep = (url.indexOf('?') >= 0) ? '&' : '?';
      var s = [];
      for (var k in params) {
        if (!params.hasOwnProperty(k)) continue;
        s.push(encodeURIComponent(k) + '=' + encodeURIComponent(String(params[k])));
      }
      return url + sep + s.join('&');
    }

    function renderUploadsList(uploads) {
      var list = qs('bt-uploads-list');
      if (!list) return;
      if (!uploads || !uploads.length) {
        list.innerHTML = '<div class="ty-strong">Нет загруженных картинок</div>';
        return;
      }

      var html = '';
      for (var i = 0; i < uploads.length; i++) {
        var u = uploads[i];
        var isActive = state.activeUploadId && (state.activeUploadId === u.upload_id);
        html += '<div style="display:flex; align-items:center; gap:8px; margin-bottom:6px;">'
            + '<img src="' + buildUrlWithParams(cfg.urls.uploadPreview, { upload_id: u.upload_id }) + '" style="width:44px; height:44px; object-fit:contain; border:1px solid #ddd; background:#fff;" />'
            + '<button type="button" class="ty-btn" data-bt-use-upload-id="' + u.upload_id + '" data-bt-pid="' + pid + '">' + (isActive ? 'Выбрано' : 'Выбрать') + '</button>'
            + '<div style="font-size:12px; line-height:1.2;">' + String(u.original_filename || ('upload #' + u.upload_id)) + '</div>'
            + '</div>';
      }
      list.innerHTML = html;

      var btns = list.querySelectorAll('[data-bt-use-upload-id]');
      for (var j = 0; j < btns.length; j++) {
        btns[j].addEventListener('click', function (e) {
          var idStr = e && e.currentTarget ? e.currentTarget.getAttribute('data-bt-use-upload-id') : '';
          var uploadId = parseInt(idStr, 10) || 0;
          if (!uploadId) return;
          setActiveUpload(uploadId);
        });
      }
    }

    function loadUploadsList() {
      if (!cfg.urls || !cfg.urls.listUploads || !cfg.urls.uploadPreview) return;
      dbg('uploads_list_load_start', { pid: pid });
      var url = buildUrlWithParams(cfg.urls.listUploads, { product_id: pid });
      fetchJson(url, { credentials: 'same-origin' })
          .then(function (json) {
            if (!json || !json.ok) {
              dbg('uploads_list_load_failed', { pid: pid, response: json });
              return;
            }
            dbg('uploads_list_load_ok', { pid: pid, count: (json.uploads || []).length });
            renderUploadsList(json.uploads || []);
          })
          .catch(function (e) {
            dbg('uploads_list_load_error', { pid: pid, message: (e && e.message) ? e.message : e });
          });
    }

    function setActiveUpload(uploadId) {
      if (!uploadId || !cfg.urls || !cfg.urls.uploadPreview) return;
      state.activeUploadId = uploadId;
      initCanvas();

      var url = buildUrlWithParams(cfg.urls.uploadPreview, { upload_id: uploadId });
      dbg('upload_pick', { pid: pid, upload_id: uploadId, url: url });

      ensureFabric(function () {
        window.fabric.Image.fromURL(url, function (img) {
          img.set({ left: PRINT.x + PRINT.w / 2, top: PRINT.y + PRINT.h / 2, originX: 'center', originY: 'center' });
          var scale = Math.min((PRINT.w * 0.8) / img.width, (PRINT.h * 0.8) / img.height);
          img.scale(scale);
          if (state.logoObject) state.canvas.remove(state.logoObject);
          state.logoObject = img;
          if (state.clipImage) {
            state.logoObject.set({ clipPath: state.clipImage });
          }
          state.canvas.add(img);
          state.canvas.setActiveObject(img);

          // Apply pending logo params from load()
          try {
            var lp = state._pendingLogoParams;
            if (lp && typeof lp === 'object') {
              if (lp.left != null) img.left = lp.left;
              if (lp.top != null) img.top = lp.top;
              if (lp.scaleX != null) img.scaleX = lp.scaleX;
              if (lp.scaleY != null) img.scaleY = lp.scaleY;
              if (lp.angle != null) img.angle = lp.angle;
              if (lp.opacity != null) img.opacity = lp.opacity;
              img.setCoords();
              if (qs('bt-img-opacity') && lp.opacity != null) qs('bt-img-opacity').value = String(lp.opacity);

              // Restore filters
              if (lp.filters && typeof lp.filters === 'object') {
                var f = lp.filters;
                var hasNumeric = (f.brightness !== null && f.brightness != null)
                    || (f.contrast !== null && f.contrast != null)
                    || (f.saturation !== null && f.saturation != null);
                if (f.preset && typeof f.preset === 'string') {
                  state.logoFilterPreset = String(f.preset);
                  if (!hasNumeric) {
                    state.logoFiltersMode = 'preset';
                    applyPresetFilter(state.logoFilterPreset);
                  }
                }
                if (qs('bt-img-bright') && f.brightness != null) qs('bt-img-bright').value = String(f.brightness);
                if (qs('bt-img-contrast') && f.contrast != null) qs('bt-img-contrast').value = String(f.contrast);
                if (qs('bt-img-sat') && f.saturation != null) qs('bt-img-sat').value = String(f.saturation);
                if (hasNumeric) {
                  state.logoFiltersMode = 'manual';
                  applyImageFilters();
                }
              }

              state._pendingLogoParams = null;
            }
          } catch (e0) {}

          clampToPrint(img);

          // Ensure stacking: logo below text, print rect on top
          try {
            if (state.logoObject && state.logoObject.bringToFront) state.logoObject.bringToFront();
            if (state.textObject && state.textObject.bringToFront) state.textObject.bringToFront();
            if (state.printRect && state.printRect.bringToFront) state.printRect.bringToFront();
          } catch (e1) {}

          state.canvas.requestRenderAll();
          loadUploadsList();
        }, { crossOrigin: 'anonymous' });
      });
    }

    function setActivePane(name) {
      var panes = document.querySelectorAll('[data-bt-pane][data-bt-pid="' + pid + '"]');
      for (var i = 0; i < panes.length; i++) {
        panes[i].style.display = (panes[i].getAttribute('data-bt-pane') === name) ? 'block' : 'none';
      }
      dbg('tab_select', { pid: pid, tab: name });
    }

    function upsertText() {
      if (!state.canvas || !window.fabric) return;
      var text = (qs('bt-text') && qs('bt-text').value) ? qs('bt-text').value : '';
      var color = (qs('bt-text-color') && qs('bt-text-color').value) ? qs('bt-text-color').value : '#000000';
      var opacity = qs('bt-text-opacity') ? Number(qs('bt-text-opacity').value || 1) : 1;
      var size = qs('bt-text-size') ? Number(qs('bt-text-size').value || 32) : 32;

      dbg('text_apply', { pid: pid, textLen: (text || '').length, color: color, opacity: opacity, size: size });

      if (!state.textObject) {
        state.textObject = new window.fabric.IText(text || ' ', {
          left: PRINT.x + 20,
          top: PRINT.y + 20,
          fontFamily: 'Arial',
          fontSize: size,
          fill: color,
          opacity: opacity
        });
        state.canvas.add(state.textObject);
      } else {
        state.textObject.set({ text: text || ' ', fill: color, opacity: opacity, fontSize: size });
      }
      state.canvas.setActiveObject(state.textObject);
      clampToPrint(state.textObject);
      state.canvas.requestRenderAll();
    }

    function applyImageFilters() {
      if (!state.logoObject || !state.logoObject.filters || !window.fabric) return;
      state.logoFiltersMode = 'manual';
      var b = qs('bt-img-bright') ? Number(qs('bt-img-bright').value || 0) : 0;
      var c = qs('bt-img-contrast') ? Number(qs('bt-img-contrast').value || 0) : 0;
      var s = qs('bt-img-sat') ? Number(qs('bt-img-sat').value || 0) : 0;

      dbg('image_filters_apply', { pid: pid, brightness: b, contrast: c, saturation: s });

      state.logoObject.filters = [
        new window.fabric.Image.filters.Brightness({ brightness: b }),
        new window.fabric.Image.filters.Contrast({ contrast: c }),
        new window.fabric.Image.filters.Saturation({ saturation: s })
      ];
      state.logoObject.applyFilters();
      state.canvas.requestRenderAll();
    }

    function applyPresetFilter(name) {
      if (!state.logoObject || !window.fabric) return;

      state.logoFilterPreset = name;
      state.logoFiltersMode = 'preset';

      var filters = [];
      if (name === 'none') {
        filters = [];
      } else if (name === 'bw') {
        filters = [new window.fabric.Image.filters.Grayscale()];
      } else if (name === 'sepia') {
        filters = [new window.fabric.Image.filters.Sepia()];
      } else if (name === 'vintage') {
        filters = [
          new window.fabric.Image.filters.Sepia(),
          new window.fabric.Image.filters.Contrast({ contrast: -0.05 }),
          new window.fabric.Image.filters.Brightness({ brightness: 0.05 })
        ];
      } else if (name === 'vivid') {
        filters = [
          new window.fabric.Image.filters.Saturation({ saturation: 0.35 }),
          new window.fabric.Image.filters.Contrast({ contrast: 0.15 })
        ];
      } else if (name === 'warm') {
        filters = [
          new window.fabric.Image.filters.Saturation({ saturation: 0.15 }),
          new window.fabric.Image.filters.Brightness({ brightness: 0.05 })
        ];
      } else if (name === 'cool') {
        filters = [
          new window.fabric.Image.filters.Saturation({ saturation: -0.15 }),
          new window.fabric.Image.filters.Contrast({ contrast: 0.05 })
        ];
      }

      dbg('image_preset_apply', { pid: pid, preset: name, filtersCount: filters.length });
      state.logoObject.filters = filters;
      state.logoObject.applyFilters();
      state.canvas.requestRenderAll();
    }

    function applyLogoOpacity() {
      if (!state.logoObject || !state.canvas) return;
      var op = qs('bt-img-opacity') ? Number(qs('bt-img-opacity').value || 1) : 1;
      if (isNaN(op)) op = 1;
      op = Math.max(0, Math.min(1, op));
      dbg('logo_opacity_apply', { pid: pid, opacity: op });
      state.logoObject.set({ opacity: op });
      state.canvas.requestRenderAll();
    }

    function resetImageFilters() {
      dbg('image_filters_reset', { pid: pid });
      state.logoFiltersMode = 'preset';
      applyPresetFilter('none');
      if (qs('bt-img-opacity')) qs('bt-img-opacity').value = '1';
      applyLogoOpacity();
    }

    function onUploadChange(e) {
      if (!state.canvas || !window.fabric) return;
      var file = e && e.target && e.target.files ? e.target.files[0] : null;
      if (!file) return;
      state.lastLogoFile = file;
      state.activeUploadId = 0;

      dbg('upload_selected', { pid: pid, name: file.name, size: file.size, type: file.type });

      var reader = new FileReader();
      reader.onload = function () {
        window.fabric.Image.fromURL(reader.result, function (img) {
          img.set({ left: PRINT.x + PRINT.w / 2, top: PRINT.y + PRINT.h / 2, originX: 'center', originY: 'center' });
          var scale = Math.min((PRINT.w * 0.8) / img.width, (PRINT.h * 0.8) / img.height);
          img.scale(scale);
          if (state.logoObject) state.canvas.remove(state.logoObject);
          state.logoObject = img;
          if (state.clipImage) {
            state.logoObject.set({ clipPath: state.clipImage });
          }
          state.canvas.add(img);
          state.canvas.setActiveObject(img);
          clampToPrint(img);
          state.canvas.requestRenderAll();

          var list = qs('bt-uploads-list');
          if (list) list.innerHTML = '<div style="overflow-wrap:anywhere; word-break:break-word;">' + file.name + '</div>';
        }, { crossOrigin: 'anonymous' });
      };
      reader.readAsDataURL(file);
    }

    function getProductAmount() {
      try {
        var byName = document.querySelector('input[name="product_data[' + pid + '][amount]"]');
        if (byName && byName.value) {
          var v1 = parseFloat(byName.value);
          if (isFinite(v1) && v1 > 0) return v1;
        }
        var byId = document.getElementById('qty_count_' + pid);
        if (byId && byId.value) {
          var v2 = parseFloat(byId.value);
          if (isFinite(v2) && v2 > 0) return v2;
        }
      } catch (e) {}
      return 1;
    }

    function buildDispatchUrl(dispatch, params) {
      var qs2 = [];
      for (var k in (params || {})) {
        if (!params.hasOwnProperty(k)) continue;
        qs2.push(encodeURIComponent(k) + '=' + encodeURIComponent(String(params[k])));
      }
      var url = dispatch + (qs2.length ? ('?' + qs2.join('&')) : '');
      try {
        if (window.Tygh && window.Tygh.fn_url) {
          return window.Tygh.fn_url(url);
        }
      } catch (e) {}
      return url;
    }

    function save(onSuccess) {
      if (!state.canvas || !cfg.urls || !cfg.urls.save) return;
      dbg('save_click', { pid: pid, hasText: !!state.textObject, hasLogo: !!state.logoObject, hasFile: !!state.lastLogoFile });
      setStatus(statusEl, 'Сохранение...', false);

      var fd = new FormData();
      fd.append('product_id', String(pid));
      fd.append('product_type', 'tshirt');

      var textValue = state.textObject ? (state.textObject.text || '') : '';
      fd.append('text_value', textValue);
      fd.append('text_params', JSON.stringify(state.textObject ? {
        left: state.textObject.left,
        top: state.textObject.top,
        scaleX: state.textObject.scaleX,
        scaleY: state.textObject.scaleY,
        angle: state.textObject.angle,
        fontFamily: state.textObject.fontFamily,
        fontSize: state.textObject.fontSize,
        fill: state.textObject.fill,
        opacity: state.textObject.opacity,
        originX: state.textObject.originX,
        originY: state.textObject.originY
      } : {}));

      var brightEl = qs('bt-img-bright');
      var contrastEl = qs('bt-img-contrast');
      var satEl = qs('bt-img-sat');

      var wantManual = (state.logoFiltersMode === 'manual');

      fd.append('logo_params', JSON.stringify(state.logoObject ? {
        left: state.logoObject.left,
        top: state.logoObject.top,
        scaleX: state.logoObject.scaleX,
        scaleY: state.logoObject.scaleY,
        angle: state.logoObject.angle,
        opacity: state.logoObject.opacity,
        filters: {
          preset: state.logoFilterPreset || 'none',
          // Store numeric filters only if sliders exist; otherwise keep null to avoid overriding preset on restore.
          brightness: (wantManual && brightEl) ? Number(brightEl.value || 0) : null,
          contrast: (wantManual && contrastEl) ? Number(contrastEl.value || 0) : null,
          saturation: (wantManual && satEl) ? Number(satEl.value || 0) : null
        }
      } : {}));

      var useUpload = qs('bt-use-upload') ? qs('bt-use-upload').checked : true;
      dbg('save_use_upload', { pid: pid, useUpload: useUpload });
      if (useUpload && state.activeUploadId) {
        fd.append('upload_id', String(state.activeUploadId));
      } else if (useUpload && state.lastLogoFile) {
        fd.append('logo_file', state.lastLogoFile);
      }

      var prevActive = state.canvas.getActiveObject();
      state.canvas.discardActiveObject();
      state.canvas.requestRenderAll();
      state.printRect.visible = false;
      state.canvas.requestRenderAll();
      var preview = state.canvas.toDataURL({ format: 'png', multiplier: 1 });
      state.printRect.visible = true;
      if (prevActive) state.canvas.setActiveObject(prevActive);
      state.canvas.requestRenderAll();
      fd.append('preview_png', preview);

      fetch(cfg.urls.save, { method: 'POST', body: fd, credentials: 'same-origin' })
          .then(function (r) {
            return r.text().then(function (txt) {
              var ct = '';
              try { ct = r.headers && r.headers.get ? (r.headers.get('content-type') || '') : ''; } catch (e0) {}
              var json = null;
              try {
                json = txt ? JSON.parse(txt) : null;
              } catch (e1) {
                var err = new Error('Non-JSON response');
                err._bt = { status: r.status, ok: r.ok, contentType: ct, snippet: String(txt || '').slice(0, 350) };
                throw err;
              }
              if (!r.ok) {
                var err2 = new Error('HTTP ' + r.status);
                err2._bt = { status: r.status, ok: r.ok, contentType: ct, snippet: String(txt || '').slice(0, 350), json: json };
                throw err2;
              }
              return json;
            });
          })
          .then(function (json) {
            if (!json || !json.ok) {
              dbg('save_failed', { pid: pid, response: json });
              setStatus(statusEl, 'Ошибка сохранения: ' + ((json && json.error) ? json.error : 'unknown'), true);
              return;
            }
            dbg('save_ok', { pid: pid, item_id: json.item_id });
            setStatus(statusEl, 'Сохранено (item_id=' + json.item_id + ')', false);
            if (typeof onSuccess === 'function') {
              try { onSuccess(json); } catch (e0) {}
            } else {
              // Update product image to saved preview (authorized only)
              try { applyPreviewToProductImage({ cacheBust: true }); } catch (e1) {}
              // Close panel to restore hidden UI blocks
              setTimeout(function () { showPanel(false); }, 250);
            }
          })
          .catch(function (e) {
            dbg('save_request_error', { pid: pid, message: (e && e.message) ? e.message : e });
            try {
              if (e && e._bt && e._bt.status) {
                // Friendly message when addon/controller is disabled/unavailable.
                if (e._bt.status === 404 || e._bt.status === 403 || e._bt.status === 302 || e._bt.status === 301) {
                  setStatus(statusEl, 'Функция временно недоступна (аддон отключен администратором).', true);
                } else {
                  setStatus(statusEl, 'Ошибка запроса (' + e._bt.status + '): ' + (e._bt.contentType || '') + '<br />' + (e._bt.snippet || ''), true);
                }
              } else {
                setStatus(statusEl, 'Ошибка запроса: ' + (e && e.message ? e.message : e), true);
              }
            } catch (e0) {
              setStatus(statusEl, 'Ошибка запроса: ' + (e && e.message ? e.message : e), true);
            }
          });
    }

    if (!btnOpen) dbg('warn_missing_btnOpen', { pid: pid });
    if (!panel) dbg('warn_missing_panel', { pid: pid });
    if (!stage) dbg('warn_missing_stage', { pid: pid });
    btnOpen && btnOpen.addEventListener('click', function () { showPanel(true); });
    btnClose && btnClose.addEventListener('click', function () { showPanel(false); });
    btnAddText && btnAddText.addEventListener('click', function () { initCanvas(); upsertText(); });
    btnSave && btnSave.addEventListener('click', function () { initCanvas(); save(); });
    btnAddToCart && btnAddToCart.addEventListener('click', function () {
      initCanvas();
      save(function () {
        // Close first, then go add to cart
        setTimeout(function () {
          try { showPanel(false); } catch (e0) {}
          var amount = getProductAmount();
          var url = buildDispatchUrl('checkout.add', { product_id: pid, amount: amount });
          window.location.href = url;
        }, 150);
      });
    });
    upload && upload.addEventListener('change', function (e) { initCanvas(); onUploadChange(e); });

    var tText = qs('bt-text');
    tText && tText.addEventListener('input', function () { dbg('text_input', { pid: pid, len: (tText.value || '').length }); });
    var tColor = qs('bt-text-color');
    tColor && tColor.addEventListener('input', function () { dbg('text_color_change', { pid: pid, color: tColor.value }); });
    var tOpacity = qs('bt-text-opacity');
    tOpacity && tOpacity.addEventListener('input', function () { dbg('text_opacity_change', { pid: pid, opacity: Number(tOpacity.value || 1) }); });
    var tSize = qs('bt-text-size');
    tSize && tSize.addEventListener('input', function () { dbg('text_size_change', { pid: pid, size: Number(tSize.value || 32) }); });

    var tabs = document.querySelectorAll('[data-bt-tab][data-bt-pid="' + pid + '"]');
    for (var i = 0; i < tabs.length; i++) {
      tabs[i].addEventListener('click', function (e) {
        var name = e && e.currentTarget ? e.currentTarget.getAttribute('data-bt-tab') : 'text';
        setActivePane(name);
        if (name === 'uploads') {
          loadUploadsList();
        }
      });
    }

    var presetBtns = document.querySelectorAll('[data-bt-filter][data-bt-pid="' + pid + '"]');
    for (var p = 0; p < presetBtns.length; p++) {
      presetBtns[p].addEventListener('click', function (e) {
        var name = e && e.currentTarget ? e.currentTarget.getAttribute('data-bt-filter') : 'none';
        initCanvas();
        applyPresetFilter(name);
      });
    }

    var opRange = qs('bt-img-opacity');
    opRange && opRange.addEventListener('input', function () { applyLogoOpacity(); });

    var reset = qs('bt-img-reset');
    reset && reset.addEventListener('click', function () { resetImageFilters(); });

    setActivePane('text');

    // If user already has saved preview for this product - replace main image right away
    try { applyPreviewToProductImage({ cacheBust: false }); } catch (e0) {}
  }

  function initAllOnDomReady() {
    try {
      if (!window.__BT__) {
        dbg('init_wait_bt_config', {});
        return false;
      }

      var keys = Object.keys(window.__BT__);
      dbg('init_scan_products', { count: keys.length, keys: keys });
      for (var i = 0; i < keys.length; i++) {
        var pid = parseInt(keys[i], 10);
        if (pid) initForProduct(pid);
      }
      return true;
    } catch (e) {
      dbg('init_exception', { message: (e && e.message) ? e.message : e });
      return false;
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllOnDomReady);
  } else {
    initAllOnDomReady();
  }

  // Apply preview replacement for catalog/minicart images (authorized user) on initial load
  try { btReplaceCatalogImages(document); } catch (e0) {}

  // And after CS-Cart AJAX renders blocks
  try {
    if (window.Tygh && window.Tygh.$ && window.Tygh.$.ceEvent) {
      window.Tygh.$.ceEvent('on', 'ce.commoninit', function (ctx) {
        try {
          btReplaceCatalogImages(ctx && ctx[0] ? ctx[0] : (ctx || document));
        } catch (e1) {}
      });
    }
  } catch (e2) {}

  // If config appears after this script, retry a few times
  (function retryInit(triesLeft) {
    if (initAllOnDomReady()) return;
    if (triesLeft <= 0) {
      dbg('init_give_up', {});
      return;
    }
    setTimeout(function () { retryInit(triesLeft - 1); }, 200);
  })(25);
})(window, document);
