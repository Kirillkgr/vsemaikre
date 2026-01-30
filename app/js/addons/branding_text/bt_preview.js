(function (window, document) {
  'use strict';

  function dbgSafe(event, payload) {
    try {
      if (window.dbg) return window.dbg(event, payload);
    } catch (e0) {}
  }

  function getGlobal() {
    try {
      return window.btGetGlobalCfg ? window.btGetGlobalCfg() : (window.__BT_GLOBAL__ || null);
    } catch (e) {
      return null;
    }
  }

  function buildUrl(url, params) {
    try {
      if (window.btBuildUrlWithParams) return window.btBuildUrlWithParams(url, params);
    } catch (e0) {}
    // fallback
    var sep = (url.indexOf('?') >= 0) ? '&' : '?';
    var s = [];
    for (var k in (params || {})) {
      if (!params.hasOwnProperty(k)) continue;
      s.push(encodeURIComponent(k) + '=' + encodeURIComponent(String(params[k])));
    }
    return url + (s.length ? (sep + s.join('&')) : '');
  }

  function parsePidFromHref(href) {
    try {
      if (window.btParseProductIdFromHref) return window.btParseProductIdFromHref(href);
    } catch (e0) {}
    if (!href) return 0;
    try {
      var m = String(href).match(/[?&]product_id=(\d+)/);
      if (m && m[1]) return parseInt(m[1], 10) || 0;
    } catch (e) {}
    return 0;
  }

  function parsePidLoose(str) {
    if (!str) return 0;
    try {
      // Common SEO patterns may include product id as trailing digits.
      // Keep it conservative: only accept 2+ digits.
      var m = String(str).match(/(?:^|[^0-9])(\d{2,})(?:\.html)?(?:$|[^0-9])/);
      if (m && m[1]) return parseInt(m[1], 10) || 0;
    } catch (e0) {}
    return 0;
  }

  function extractPidFromListingContext(linkEl, imgEl) {
    let pid;
    // 1) href with query
    pid = parsePidFromHref(linkEl && linkEl.getAttribute ? linkEl.getAttribute('href') : '');
    if (pid) return pid;

    // 2) data attributes on img/link
    try {
      var v1 = imgEl && imgEl.getAttribute ? (imgEl.getAttribute('data-ca-product-id') || imgEl.getAttribute('data-product-id') || imgEl.getAttribute('data-bt-product-id')) : '';
      pid = parseInt(v1 || '0', 10) || 0;
      if (pid) return pid;
    } catch (e1) {}
    try {
      var v2 = linkEl && linkEl.getAttribute ? (linkEl.getAttribute('data-ca-product-id') || linkEl.getAttribute('data-product-id') || linkEl.getAttribute('data-bt-product-id')) : '';
      pid = parseInt(v2 || '0', 10) || 0;
      if (pid) return pid;
    } catch (e2) {}

    // 3) hidden input in nearest product form (common/product_data.tpl)
    try {
      var root = null;
      if (linkEl && linkEl.closest) root = linkEl.closest('form');
      if (!root && imgEl && imgEl.closest) root = imgEl.closest('form');
      if (root && root.querySelector) {
        var inp = root.querySelector('input[name*="[product_id]"]');
        if (inp && inp.value) {
          pid = parseInt(inp.value, 10) || 0;
          if (pid) return pid;
        }
      }
    } catch (e3) {}

    // 4) id patterns like list_image_update_prefix123
    try {
      var host = null;
      // Be strict here: many elements contain random ids with digits (uniqid, image ids, block ids) which are NOT product_id.
      // Accept only known CS-Cart patterns that commonly embed product_id.
      if (linkEl && linkEl.closest) host = linkEl.closest('[id^="det_img_"], [id^="product_"]');
      if (!host && imgEl && imgEl.closest) host = imgEl.closest('[id^="det_img_"], [id^="product_"]');
      if (host && host.id) {
        var id = String(host.id);
        var mDet = id.match(/^det_img_(\d+)$/);
        if (mDet && mDet[1]) {
          pid = parseInt(mDet[1], 10) || 0;
          if (pid) return pid;
        }
        var mProd = id.match(/^product_(\d+)$/);
        if (mProd && mProd[1]) {
          pid = parseInt(mProd[1], 10) || 0;
          if (pid) return pid;
        }
      }
    } catch (e4) {}

    return 0;
  }

  function isBranded(pid) {
    try {
      if (window.btIsProductBranded) return window.btIsProductBranded(pid);
    } catch (e0) {}
    return false;
  }

  function applyPreviewToListingImage(linkEl, imgEl) {
    var g = getGlobal();
    dbgSafe('listing_try', {
      hasGlobal: !!g,
      user_id: g ? g.user_id : null,
      hasPreviewUrl: g ? !!g.previewForProduct : null
    });
    if (!g || !g.previewForProduct || !g.user_id) return;
    if (!linkEl || !imgEl) return;
    if (imgEl.getAttribute && imgEl.getAttribute('data-bt-preview-applied') === 'Y') return;

    var pid = extractPidFromListingContext(linkEl, imgEl);
    if (!pid) return;

    // To avoid massive HEAD 404 spam on catalog/home, check only products that were branded by this user.
    if (!isBranded(pid)) {
      dbgSafe('listing_skip_not_branded', { pid: pid });
      return;
    }

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
    var url = buildUrl(g.previewForProduct, params);

    // If image is wrapped by CS-Cart previewer link (zoom/lightbox), also update the large image href.
    // common/image.tpl: <a class="... cm-previewer ty-previewer" href="detailed_image_path">...
    var shouldUpdatePreviewerLink = false;
    try {
      var cls = (linkEl.className || '');
      if (typeof cls === 'string' && (cls.indexOf('cm-previewer') >= 0 || cls.indexOf('ty-previewer') >= 0 || cls.indexOf('cm-image-previewer') >= 0)) {
        shouldUpdatePreviewerLink = true;
      }
    } catch (e00) {}
    // Fallback: if link has data-ca-image-width/height, it's a previewer.
    try {
      if (!shouldUpdatePreviewerLink && linkEl.getAttribute) {
        if (linkEl.getAttribute('data-ca-image-width') || linkEl.getAttribute('data-ca-image-height')) {
          shouldUpdatePreviewerLink = true;
        }
      }
    } catch (e01) {}

    var largeParams = { product_id: pid, _t: 0, w: 1200, h: 1200 };
    var largeUrl = buildUrl(g.previewForProduct, largeParams);

    // Keep original src for fallback.
    origSrc = imgEl.getAttribute('data-bt-orig-src') || imgEl.src;
    imgEl.setAttribute('data-bt-orig-src', origSrc);

    // Keep original previewer href for fallback.
    var origHref = '';
    try {
      if (shouldUpdatePreviewerLink) {
        origHref = linkEl.getAttribute('data-bt-orig-href') || linkEl.getAttribute('href') || '';
        if (origHref) linkEl.setAttribute('data-bt-orig-href', origHref);
      }
    } catch (e02) {}

    // Avoid console 404 spam: precheck by HEAD and only swap if preview exists.
    window.__BT_PREVIEW_HEAD__ = window.__BT_PREVIEW_HEAD__ || {};
    var sw = (size && size.w) ? size.w : 0;
    var sh = (size && size.h) ? size.h : 0;
    var key = String(pid) + '|' + String(sw) + 'x' + String(sh);
    dbgSafe('listing_head_start', { pid: pid, url: url, w: sw, h: sh, key: key });

    if (!window.__BT_PREVIEW_HEAD__[key]) {
      window.__BT_PREVIEW_HEAD__[key] = fetch(url, { method: 'HEAD', credentials: 'same-origin' })
          .then(function (r) { return !!(r && r.ok); })
          .catch(function () { return false; });
    }

    window.__BT_PREVIEW_HEAD__[key].then(function (ok) {
      dbgSafe('listing_head_result', { pid: pid, ok: ok, key: key });
      if (!ok) return;
      imgEl.src = url;
      imgEl.onerror = function () {
        imgEl.src = origSrc;
        try {
          if (shouldUpdatePreviewerLink && origHref) {
            linkEl.setAttribute('href', origHref);
          }
        } catch (e10) {}
      };

      // Update previewer link to use large branded preview (so zoom shows detailed branded image).
      try {
        if (shouldUpdatePreviewerLink) {
          linkEl.setAttribute('href', largeUrl);
          linkEl.setAttribute('data-ca-image-width', String(largeParams.w));
          linkEl.setAttribute('data-ca-image-height', String(largeParams.h));
          dbgSafe('listing_previewer_href_swap', { pid: pid, href: largeUrl });
        }
      } catch (e11) {}

      // Keep generate_image lazy-load paths consistent when present.
      try {
        if (imgEl.getAttribute && imgEl.getAttribute('data-ca-image-path')) {
          imgEl.setAttribute('data-ca-image-path', url);
        }
      } catch (e12) {}

      try { imgEl.setAttribute('data-bt-preview-applied', 'Y'); } catch (e0) {}
      dbgSafe('listing_swap', { pid: pid, url: url });
    });
  }

  function replaceCatalogImages(context) {
    var g = getGlobal();
    dbgSafe('catalog_scan_start', {
      hasGlobal: !!g,
      user_id: g ? g.user_id : null,
      hasPreviewUrl: g ? !!g.previewForProduct : null
    });
    if (!g || !g.previewForProduct || !g.user_id) return;
    context = context || document;

    // SEO-friendly: product links on storefront may not contain products.view or product_id.
    // Scan product card images and then locate their closest anchor.
    var imgs = context.querySelectorAll ? context.querySelectorAll('img.cm-image, img.ty-pict') : [];
    dbgSafe('catalog_scan_imgs', { count: imgs ? imgs.length : 0 });
    for (var i = 0; i < imgs.length; i++) {
      var img = imgs[i];
      if (!img) continue;
      var a = null;
      try {
        if (img.closest) a = img.closest('a[href]');
      } catch (e0) {}
      if (!a) continue;
      applyPreviewToListingImage(a, img);
    }
  }

  function applyPreviewToMarkedImages(context) {
    var g = getGlobal();
    dbgSafe('marked_scan_start', {
      hasGlobal: !!g,
      user_id: g ? g.user_id : null,
      hasPreviewUrl: g ? !!g.previewForProduct : null
    });
    if (!g || !g.previewForProduct || !g.user_id) return;
    context = context || document;

    var imgs = context.querySelectorAll ? context.querySelectorAll('img[data-bt-preview-url]') : [];
    dbgSafe('marked_scan_imgs', { count: imgs ? imgs.length : 0 });
    for (var i = 0; i < imgs.length; i++) {
      var imgEl = imgs[i];
      if (!imgEl || !imgEl.getAttribute) continue;
      if (imgEl.getAttribute('data-bt-preview-applied') === 'Y') continue;

      var url = imgEl.getAttribute('data-bt-preview-url') || '';
      // Some templates/autoescaping may leave '&amp;' in attribute values; normalize to avoid malformed requests.
      try { url = String(url).replace(/&amp;/g, '&'); } catch (e00) {}
      if (!url) continue;

      var pid = parseInt(imgEl.getAttribute('data-bt-product-id') || '0', 10) || 0;

      if (pid && !isBranded(pid)) {
        dbgSafe('marked_skip_not_branded', { pid: pid });
        continue;
      }

      // Cache by pid + requested size (w/h in query string).
      var w = 0;
      var h = 0;
      try {
        var mw = String(url).match(/[?&]w=(\d+)/);
        var mh = String(url).match(/[?&]h=(\d+)/);
        if (mw && mw[1]) w = parseInt(mw[1], 10) || 0;
        if (mh && mh[1]) h = parseInt(mh[1], 10) || 0;
      } catch (e0) {}

      // Keep original src for fallback.
      var origSrc = imgEl.getAttribute('data-bt-orig-src') || imgEl.getAttribute('src') || '';
      imgEl.setAttribute('data-bt-orig-src', origSrc);

      window.__BT_PREVIEW_HEAD__ = window.__BT_PREVIEW_HEAD__ || {};
      var key = String(pid) + '|' + String(w) + 'x' + String(h);
      if (!window.__BT_PREVIEW_HEAD__[key]) {
        dbgSafe('marked_head_start', { pid: pid, url: url, w: w, h: h, key: key });
        window.__BT_PREVIEW_HEAD__[key] = fetch(url, { method: 'HEAD', credentials: 'same-origin' })
            .then(function (r) { return !!(r && r.ok); })
            .catch(function () { return false; });
      }

      window.__BT_PREVIEW_HEAD__[key].then(function (ok) {
        dbgSafe('marked_head_result', { pid: pid, ok: ok, key: key });
        if (!ok) return;
        try {
          imgEl.setAttribute('data-bt-preview-applied', 'Y');
          imgEl.src = url;
          imgEl.onerror = function () {
            imgEl.src = origSrc;
          };
          dbgSafe('marked_swap', { pid: pid, url: url });
        } catch (e1) {}
      });
    }
  }

  function initPreview(root) {
    try {
      replaceCatalogImages(root || document);
      applyPreviewToMarkedImages(root || document);
    } catch (e0) {}
  }

  // Export
  try { window.btReplaceCatalogImages = replaceCatalogImages; } catch (e1) {}
  try { window.btApplyPreviewToMarkedImages = applyPreviewToMarkedImages; } catch (e2) {}

  // Initial load + CS-Cart AJAX updates
  initPreview(document);
  try {
    if (window.Tygh && window.Tygh.$ && window.Tygh.$.ceEvent) {
      window.Tygh.$.ceEvent('on', 'ce.commoninit', function (ctx) {
        try {
          var root = (ctx && ctx[0]) ? ctx[0] : (ctx || document);
          initPreview(root);
        } catch (e3) {}
      });
    }
  } catch (e4) {}

  dbgSafe('bt_preview_loaded', { readyState: document.readyState });
})(window, document);
