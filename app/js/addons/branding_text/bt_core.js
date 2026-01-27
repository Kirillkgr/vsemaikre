(function (window, document) {
  'use strict';

  window.__BT_LOGS__ = window.__BT_LOGS__ || [];
  window.__BT_INIT__ = window.__BT_INIT__ || {};

  function dbg(event, payload) {
    var debug = false;
    var force = false;
    try { debug = !!window.__BT_DEBUG__; } catch (e0) {}
    try { force = !!window.__BT_FORCE_LOGS__; } catch (e1) {}
    if (!debug && !force) return;
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
    } catch (e2) {}
  }

  // Export dbg globally (designer.js and preview modules rely on it)
  try { window.dbg = dbg; } catch (e3) {}

  dbg('bt_core_loaded', { readyState: document.readyState });

  // Safari (and any native selector engine) cannot handle jQuery-only pseudos like :visible
  // inside Element.matches(). Some CS-Cart core code may pass such selectors.
  // Fallback to jQuery .is() only when native matches throws a SyntaxError.
  try {
    if (window.Element && window.Element.prototype) {
      var _btMatches = window.Element.prototype.matches;
      if (_btMatches && !_btMatches.__bt_patched__) {
        window.Element.prototype.matches = function (selector) {
          try {
            return _btMatches.call(this, selector);
          } catch (e) {
            try {
              if (e && (e.name === 'SyntaxError' || String(e).indexOf('SyntaxError') >= 0)) {
                if (window.Tygh && window.Tygh.$) {
                  return window.Tygh.$(this).is(selector);
                }
              }
            } catch (e2) {}
            throw e;
          }
        };
        window.Element.prototype.matches.__bt_patched__ = true;
      }
    }
  } catch (e0) {}

  function btGetGlobalCfg() {
    try {
      return window.__BT_GLOBAL__ || null;
    } catch (e) {
      return null;
    }
  }

  function btGetBrandedStorageKey() {
    try {
      var g = btGetGlobalCfg();
      var uid = g && g.user_id ? String(g.user_id) : '0';
      return 'bt_branded_products_' + uid;
    } catch (e0) {
      return 'bt_branded_products_0';
    }
  }

  function btGetBrandedProductsSet() {
    var set = {};
    try {
      if (!window.localStorage) return set;
      var raw = window.localStorage.getItem(btGetBrandedStorageKey()) || '';
      if (!raw) return set;
      var arr = JSON.parse(raw);
      if (!arr || !arr.length) return set;
      for (var i = 0; i < arr.length; i++) {
        var pid = parseInt(arr[i], 10) || 0;
        if (pid) set[String(pid)] = true;
      }
    } catch (e1) {}
    return set;
  }

  function btMarkProductBranded(productId) {
    try {
      var pid = parseInt(productId, 10) || 0;
      if (!pid) return;
      if (!window.localStorage) return;
      var key = btGetBrandedStorageKey();
      var set = btGetBrandedProductsSet();
      set[String(pid)] = true;
      var arr = Object.keys(set);
      // keep it small (latest ~500 ids)
      if (arr.length > 500) arr = arr.slice(arr.length - 500);
      window.localStorage.setItem(key, JSON.stringify(arr));
      dbg('branded_mark', { pid: pid, key: key, count: arr.length });
    } catch (e0) {}
  }

  function btIsProductBranded(productId) {
    try {
      var pid = parseInt(productId, 10) || 0;
      if (!pid) return false;
      var set = btGetBrandedProductsSet();
      return !!set[String(pid)];
    } catch (e0) {
      return false;
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

  // Export helpers globally for backward compatibility
  try { window.btGetGlobalCfg = btGetGlobalCfg; } catch (e4) {}
  try { window.btMarkProductBranded = btMarkProductBranded; } catch (e5) {}
  try { window.btIsProductBranded = btIsProductBranded; } catch (e6) {}
  try { window.btBuildUrlWithParams = btBuildUrlWithParams; } catch (e7) {}
  try { window.btParseProductIdFromHref = btParseProductIdFromHref; } catch (e8) {}
})(window, document);
