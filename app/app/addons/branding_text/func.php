<?php

use Tygh\Registry;
use Tygh\Session;

function fn_branding_text_debug_log($event, array $context = [])
{
    if (!defined('AREA') || AREA !== 'C') {
        return;
    }

    $enabled = (bool) Registry::ifGet('config.tweaks.branding_text_debug', false);
    if (!$enabled && empty($_REQUEST['bt_debug'])) {
        return;
    }

    $line = [
        'ts' => date('c'),
        'event' => (string) $event,
        'context' => $context,
    ];

    $var_dir = (string) Registry::get('config.dir.var');
    $file = rtrim($var_dir, '/\\') . '/branding_text_debug.log';
    @file_put_contents($file, json_encode($line, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
}

function fn_branding_text_get_owner_context_from_auth(array $auth)
{
    $user_id = !empty($auth['user_id']) ? (int) $auth['user_id'] : 0;
    $session_id = '';
    if ($user_id === 0) {
        $guest_id = !empty($_COOKIE['bt_guest_id']) ? (string) $_COOKIE['bt_guest_id'] : '';
        if (!$guest_id && isset(\Tygh\Tygh::$app['session']['bt_guest_id'])) {
            $guest_id = (string) \Tygh\Tygh::$app['session']['bt_guest_id'];
        }
        if (!$guest_id) {
            $guest_id = Session::getId();
        }
        $session_id = $guest_id;
    } else {
        $session_id = Session::getId();
    }
    $company_id = (int) Registry::get('runtime.company_id');

    return [$company_id, $user_id, $session_id];
}

function fn_branding_text_table_exists_for_addon($table_name)
{
    $prefix = (string) Registry::get('config.table_prefix');
    $needle = $prefix . $table_name;
    $found = db_get_field('SHOW TABLES LIKE ?s', $needle);

    return !empty($found);
}

function fn_branding_text_build_preview_for_product_url($product_id, $w = 0, $h = 0, $t = 0)
{
    $product_id = (int) $product_id;
    $w = (int) $w;
    $h = (int) $h;
    $t = (int) $t;

    $q = 'branding_text.preview_for_product?product_id=' . $product_id;
    if ($w > 0 && $h > 0) {
        $q .= '&w=' . $w . '&h=' . $h;
    }
    $q .= '&_t=' . $t;

    return fn_url($q, 'C');
}

function fn_branding_text_get_runtime_cache_key(array $auth)
{
    list($company_id, $user_id, $session_id) = fn_branding_text_get_owner_context_from_auth($auth);

    return $company_id . '|' . $user_id . '|' . $session_id;
}

function fn_branding_text_prime_preview_map(array $product_ids, array $auth)
{
    $cache = & fn_branding_text_get_preview_map_cache();

    $product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids))));
    if (!$product_ids) {
        return;
    }

    $key = fn_branding_text_get_runtime_cache_key($auth);
    if (!isset($cache[$key])) {
        $cache[$key] = [];
    }

    list($company_id, $user_id, $session_id) = fn_branding_text_get_owner_context_from_auth($auth);

    $missing = [];
    foreach ($product_ids as $pid) {
        if (!isset($cache[$key][$pid])) {
            $missing[] = $pid;
        }
    }

    if (!$missing) {
        return;
    }

    if (!fn_branding_text_table_exists_for_addon('branding_text_items')) {
        foreach ($missing as $pid) {
            $cache[$key][$pid] = 0;
        }
        return;
    }

    $company_ids = [$company_id, 0];

    if ($user_id > 0) {
        $rows = db_get_array(
            'SELECT product_id, MAX(updated_at) AS updated_at'
            . ' FROM ?:branding_text_items'
            . ' WHERE company_id IN (?n) AND product_id IN (?n) AND preview_path <> ?s'
            . ' AND (user_id = ?i OR session_id = ?s)'
            . ' GROUP BY product_id',
            $company_ids,
            $missing,
            '',
            $user_id,
            $session_id
        );
    } else {
        $legacy_session_id = Session::getId();
        $session_ids = array_values(array_unique(array_filter([(string) $session_id, (string) $legacy_session_id])));
        $rows = db_get_array(
            'SELECT product_id, MAX(updated_at) AS updated_at'
            . ' FROM ?:branding_text_items'
            . ' WHERE company_id IN (?n) AND product_id IN (?n) AND preview_path <> ?s'
            . ' AND session_id IN (?n)'
            . ' GROUP BY product_id',
            $company_ids,
            $missing,
            '',
            $session_ids
        );
    }

    $map = [];
    foreach ($rows as $r) {
        $pid = !empty($r['product_id']) ? (int) $r['product_id'] : 0;
        if (!$pid) {
            continue;
        }
        $map[$pid] = !empty($r['updated_at']) ? (int) $r['updated_at'] : 0;
    }

    foreach ($missing as $pid) {
        $cache[$key][$pid] = isset($map[$pid]) ? $map[$pid] : 0;
    }
}

function fn_branding_text_get_preview_updated_at($product_id, array $auth)
{
    $cache = & fn_branding_text_get_preview_map_cache();

    $pid = (int) $product_id;
    if (!$pid) {
        return 0;
    }

    $key = fn_branding_text_get_runtime_cache_key($auth);
    if (isset($cache[$key]) && array_key_exists($pid, $cache[$key])) {
        return (int) $cache[$key][$pid];
    }

    fn_branding_text_prime_preview_map([$pid], $auth);

    if (!isset($cache[$key])) {
        return 0;
    }

    return !empty($cache[$key][$pid]) ? (int) $cache[$key][$pid] : 0;
}

function fn_branding_text_apply_preview_to_pair(array &$pair, $product_id, array $auth)
{
    $pid = (int) $product_id;
    if (!$pid) {
        return;
    }

    $t = fn_branding_text_get_preview_updated_at($pid, $auth);
    if (!$t) {
        fn_branding_text_debug_log('apply_preview_skip_no_preview', [
            'product_id' => $pid,
            'user_id' => !empty($auth['user_id']) ? (int) $auth['user_id'] : 0,
        ]);
        return;
    }

    if (!isset($pair['icon']) || !is_array($pair['icon'])) {
        $pair['icon'] = [];
    }
    if (!isset($pair['detailed']) || !is_array($pair['detailed'])) {
        $pair['detailed'] = [];
    }

    $iw = !empty($pair['icon']['image_x']) ? (int) $pair['icon']['image_x'] : 0;
    $ih = !empty($pair['icon']['image_y']) ? (int) $pair['icon']['image_y'] : 0;
    $dw = !empty($pair['detailed']['image_x']) ? (int) $pair['detailed']['image_x'] : 0;
    $dh = !empty($pair['detailed']['image_y']) ? (int) $pair['detailed']['image_y'] : 0;

    $icon_url = fn_branding_text_build_preview_for_product_url($pid, $iw, $ih, $t);
    $detailed_url = fn_branding_text_build_preview_for_product_url($pid, $dw, $dh, $t);

    $pair['icon']['image_path'] = $icon_url;
    $pair['detailed']['image_path'] = $detailed_url;

    // IMPORTANT:
    // fn_image_to_display() will generate a thumbnail from relative_path/absolute_path when width/height are requested.
    // When using a dynamic preview URL, we must prevent thumbnail generation from the original product image.
    $pair['icon']['relative_path'] = '';
    $pair['icon']['absolute_path'] = '';
    $pair['detailed']['relative_path'] = '';
    $pair['detailed']['absolute_path'] = '';

    // Populate protocol-specific paths for compatibility with any template/customizations.
    $pair['icon']['http_image_path'] = $icon_url;
    $pair['icon']['https_image_path'] = $icon_url;
    $pair['detailed']['http_image_path'] = $detailed_url;
    $pair['detailed']['https_image_path'] = $detailed_url;

    fn_branding_text_debug_log('apply_preview_ok', [
        'product_id' => $pid,
        't' => $t,
        'icon_url' => $icon_url,
    ]);
}

function fn_branding_text_set_pair_object_meta(array &$pair, $product_id)
{
    $pid = (int) $product_id;
    if (!$pid) {
        return;
    }

    if (!isset($pair['object_type'])) {
        $pair['object_type'] = 'product';
    }
    if (!isset($pair['object_id'])) {
        $pair['object_id'] = $pid;
    }

    if (isset($pair['icon']) && is_array($pair['icon'])) {
        if (!isset($pair['icon']['object_type'])) {
            $pair['icon']['object_type'] = 'product';
        }
        if (!isset($pair['icon']['object_id'])) {
            $pair['icon']['object_id'] = $pid;
        }
    }

    if (isset($pair['detailed']) && is_array($pair['detailed'])) {
        if (!isset($pair['detailed']['object_type'])) {
            $pair['detailed']['object_type'] = 'product';
        }
        if (!isset($pair['detailed']['object_id'])) {
            $pair['detailed']['object_id'] = $pid;
        }
    }
}

function &fn_branding_text_get_preview_map_cache()
{
    static $cache = [];
    return $cache;
}

function fn_branding_text_get_products_post(&$products, $params, $lang_code)
{
    if (empty($products) || !is_array($products)) {
        return;
    }

    if (!defined('AREA') || AREA !== 'C') {
        return;
    }

    /** @var array $auth */
    global $auth;

    if (!is_array($auth)) {
        $auth = [];
    }

    if (empty($auth['user_id']) && !empty($_SESSION['auth']) && is_array($_SESSION['auth'])) {
        $auth = $_SESSION['auth'];
        fn_branding_text_debug_log('auth_fallback_session', [
            'hook' => 'get_products_post',
            'user_id' => !empty($auth['user_id']) ? (int) $auth['user_id'] : 0,
        ]);
    }

    if (empty($auth['user_id'])) {
        return;
    }

    fn_branding_text_debug_log('get_products_post_enter', [
        'count' => is_array($products) ? count($products) : 0,
        'dispatch' => !empty($_REQUEST['dispatch']) ? (string) $_REQUEST['dispatch'] : '',
        'user_id' => (int) $auth['user_id'],
    ]);

    $product_ids = [];
    $missing_main_pair_ids = [];
    foreach ($products as $p) {
        if (is_array($p) && !empty($p['product_id'])) {
            $pid = (int) $p['product_id'];
            $product_ids[] = $pid;
            if (empty($p['main_pair']) || !is_array($p['main_pair'])) {
                $missing_main_pair_ids[] = $pid;
            }
        }
    }
    if (!$product_ids) {
        return;
    }

    // Some blocks/listings may not preload images into product data.
    // Ensure main_pair is present so templates that render via common/image.tpl can display correct images.
    if ($missing_main_pair_ids && function_exists('fn_get_image_pairs')) {
        $pairs = fn_get_image_pairs(array_unique($missing_main_pair_ids), 'product', 'M', true, true, $lang_code);
        foreach ($products as &$p) {
            if (!is_array($p) || empty($p['product_id'])) {
                continue;
            }
            $pid = (int) $p['product_id'];
            if (!empty($p['main_pair']) && is_array($p['main_pair'])) {
                continue;
            }
            if (isset($pairs[$pid]) && is_array($pairs[$pid]) && $pairs[$pid]) {
                $first = reset($pairs[$pid]);
                if (is_array($first)) {
                    $p['main_pair'] = $first;
                }
            }
        }
        unset($p);
    }

    fn_branding_text_prime_preview_map($product_ids, $auth);

    foreach ($products as &$p) {
        if (!is_array($p) || empty($p['product_id'])) {
            continue;
        }
        $pid = (int) $p['product_id'];

        if (!empty($p['main_pair']) && is_array($p['main_pair'])) {
            fn_branding_text_set_pair_object_meta($p['main_pair'], $pid);
            fn_branding_text_apply_preview_to_pair($p['main_pair'], $pid, $auth);

            if (!empty($_REQUEST['bt_debug'])) {
                fn_branding_text_debug_log('after_main_pair_apply', [
                    'product_id' => $pid,
                    'icon_image_path' => !empty($p['main_pair']['icon']['image_path']) ? (string) $p['main_pair']['icon']['image_path'] : '',
                    'icon_relative_path' => !empty($p['main_pair']['icon']['relative_path']) ? (string) $p['main_pair']['icon']['relative_path'] : '',
                ]);
            }
        }

        if (!empty($p['image_pairs']) && is_array($p['image_pairs'])) {
            foreach ($p['image_pairs'] as &$pair) {
                if (!is_array($pair)) {
                    continue;
                }
                fn_branding_text_set_pair_object_meta($pair, $pid);
                fn_branding_text_apply_preview_to_pair($pair, $pid, $auth);
            }
            unset($pair);
        }
    }
    unset($p);
}

function fn_branding_text_get_product_data_post(&$product_data, $auth, $preview, $lang_code)
{
    if (empty($product_data) || empty($product_data['product_id'])) {
        return;
    }

    if (!empty($_REQUEST['bt_constructor']) && $_REQUEST['bt_constructor'] === 'Y') {
        return;
    }

    if (!defined('AREA') || AREA !== 'C') {
        return;
    }
    $pid = (int) $product_data['product_id'];
    fn_branding_text_prime_preview_map([$pid], $auth);

    if (!empty($product_data['main_pair']) && is_array($product_data['main_pair'])) {
        fn_branding_text_apply_preview_to_pair($product_data['main_pair'], $pid, $auth);
    }
}

function fn_branding_text_get_cart_product_data($product_id, &$pdata, $product, $auth, $cart, $hash)
{
    if (!defined('AREA') || AREA !== 'C') {
        return;
    }

    $pid = (int) $product_id;
    if (!$pid) {
        return;
    }

    if (!is_array($auth)) {
        $auth = [];
    }

    if (empty($auth['user_id']) && !empty($_SESSION['auth']) && is_array($_SESSION['auth'])) {
        $auth = $_SESSION['auth'];
        fn_branding_text_debug_log('auth_fallback_session', [
            'hook' => 'get_cart_product_data',
            'user_id' => !empty($auth['user_id']) ? (int) $auth['user_id'] : 0,
            'product_id' => $pid,
        ]);
    }

    if (empty($auth['user_id'])) {
        return;
    }

    fn_branding_text_prime_preview_map([$pid], $auth);

    if (!empty($pdata['main_pair']) && is_array($pdata['main_pair'])) {
        fn_branding_text_set_pair_object_meta($pdata['main_pair'], $pid);
        fn_branding_text_apply_preview_to_pair($pdata['main_pair'], $pid, $auth);
    }
}

function fn_branding_text_image_to_display_post(&$image_data, $images, $image_width, $image_height)
{
    if (!is_array($image_data) || !$image_data || !is_array($images) || !$images) {
        return;
    }

    if (!empty($_REQUEST['bt_constructor']) && $_REQUEST['bt_constructor'] === 'Y') {
        return;
    }

    if (!empty($_REQUEST['dispatch']) && $_REQUEST['dispatch'] === 'branding_text.constructor') {
        return;
    }

    if (!defined('AREA') || AREA !== 'C') {
        return;
    }

    if (!empty($_REQUEST['bt_force_preview'])) {
        $forced_pid = !empty($_REQUEST['bt_force_pid']) ? (int) $_REQUEST['bt_force_pid'] : 0;
        if ($forced_pid <= 0) {
            $forced_pid = 279;
        }

        $w = !empty($image_data['width']) ? (int) $image_data['width'] : 0;
        $h = !empty($image_data['height']) ? (int) $image_data['height'] : 0;

        $image_data['image_path'] = fn_branding_text_build_preview_for_product_url($forced_pid, $w, $h, TIME);
        $image_data['detailed_image_path'] = fn_branding_text_build_preview_for_product_url($forced_pid, 1200, 1200, TIME);
        $image_data['generate_image'] = false;

        $var_dir = (string) Registry::get('config.dir.var');
        $file = rtrim($var_dir, '/\\') . '/branding_text_debug.log';
        @file_put_contents(
            $file,
            json_encode([
                'ts' => date('c'),
                'event' => 'force_image_to_display_replaced',
                'context' => [
                    'forced_pid' => $forced_pid,
                    'w' => $w,
                    'h' => $h,
                    'src' => $image_data['image_path'],
                ],
            ], JSON_UNESCAPED_UNICODE) . "\n",
            FILE_APPEND
        );

        return;
    }

    $object_type = '';
    $object_id = 0;

    if (!empty($images['object_type'])) {
        $object_type = (string) $images['object_type'];
        $object_id = !empty($images['object_id']) ? (int) $images['object_id'] : 0;
    }

    if ((!$object_type || !$object_id) && !empty($images['icon']) && is_array($images['icon']) && !empty($images['icon']['object_type'])) {
        $object_type = (string) $images['icon']['object_type'];
        $object_id = !empty($images['icon']['object_id']) ? (int) $images['icon']['object_id'] : 0;
    }

    if ((!$object_type || !$object_id) && !empty($images['detailed']) && is_array($images['detailed']) && !empty($images['detailed']['object_type'])) {
        $object_type = (string) $images['detailed']['object_type'];
        $object_id = !empty($images['detailed']['object_id']) ? (int) $images['detailed']['object_id'] : 0;
    }

    if ($object_type !== 'product' || !$object_id) {
        fn_branding_text_debug_log('image_to_display_skip_not_product', [
            'object_type' => $object_type,
            'object_id' => $object_id,
        ]);
        return;
    }

    /** @var array $auth */
    global $auth;

    if (!is_array($auth)) {
        $auth = [];
    }

    if (empty($auth['user_id']) && !empty($_SESSION['auth']) && is_array($_SESSION['auth'])) {
        $auth = $_SESSION['auth'];
        fn_branding_text_debug_log('auth_fallback_session', [
            'hook' => 'image_to_display_post',
            'user_id' => !empty($auth['user_id']) ? (int) $auth['user_id'] : 0,
            'product_id' => $object_id,
        ]);
    }

    $t = fn_branding_text_get_preview_updated_at($object_id, $auth);
    if (!$t) {
        fn_branding_text_debug_log('image_to_display_no_preview', [
            'product_id' => $object_id,
            'company_id' => (int) Registry::get('runtime.company_id'),
            'user_id' => !empty($auth['user_id']) ? (int) $auth['user_id'] : 0,
            'session_id' => substr((string) session_id(), 0, 8),
        ]);
        return;
    }

    $w = !empty($image_data['width']) ? (int) $image_data['width'] : 0;
    $h = !empty($image_data['height']) ? (int) $image_data['height'] : 0;

    $image_data['image_path'] = fn_branding_text_build_preview_for_product_url($object_id, $w, $h, $t);
    $image_data['detailed_image_path'] = fn_branding_text_build_preview_for_product_url($object_id, 1200, 1200, $t);
    $image_data['generate_image'] = false;

    fn_branding_text_debug_log('image_to_display_replaced', [
        'product_id' => $object_id,
        't' => $t,
        'image_path' => $image_data['image_path'],
        'detailed_image_path' => $image_data['detailed_image_path'],
    ]);
}
