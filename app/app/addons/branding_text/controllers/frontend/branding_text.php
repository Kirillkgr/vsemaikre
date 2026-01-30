<?php
if (!defined('BOOTSTRAP')) { die('Access denied'); }

use Tygh\Registry;
use Tygh\Session;

function fn_branding_text_json_response($payload, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function fn_branding_text_get_owner_context($auth)
{
    if (!is_array($auth)) {
        $auth = [];
    }
    if (empty($auth['user_id']) && isset(\Tygh\Tygh::$app['session']['auth']) && is_array(\Tygh\Tygh::$app['session']['auth'])) {
        $auth = \Tygh\Tygh::$app['session']['auth'];
    }

    $user_id = !empty($auth['user_id']) ? (int) $auth['user_id'] : 0;
    $session_id = '';
    if ($user_id === 0) {
        $guest_id = !empty($_COOKIE['bt_guest_id']) ? (string) $_COOKIE['bt_guest_id'] : '';
        if (!$guest_id && isset(\Tygh\Tygh::$app['session']['bt_guest_id'])) {
            $guest_id = (string) \Tygh\Tygh::$app['session']['bt_guest_id'];
        }
        if (!$guest_id) {
            try {
                $guest_id = 'btg_' . bin2hex(random_bytes(16));
            } catch (\Exception $e) {
                $guest_id = 'btg_' . md5(uniqid('', true));
            }
        }

        @setcookie('bt_guest_id', $guest_id, time() + 60 * 60 * 24 * 30, '/');
        if (isset(\Tygh\Tygh::$app['session'])) {
            \Tygh\Tygh::$app['session']['bt_guest_id'] = $guest_id;
        }
        $session_id = $guest_id;
    } else {
        $session_id = Session::getId();
    }
    $company_id = (int) Registry::get('runtime.company_id');

    return [$company_id, $user_id, $session_id];
}

function fn_branding_text_get_files_root_dir()
{
    $dir = Registry::get('config.dir.files');
    return rtrim($dir, '/\\');
}

function fn_branding_text_ensure_dir($path)
{
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}

function fn_branding_text_table_exists($table_name)
{
    $prefix = (string) Registry::get('config.table_prefix');
    $needle = $prefix . $table_name;
    $found = db_get_field('SHOW TABLES LIKE ?s', $needle);
    return !empty($found);
}

function fn_branding_text_save_data_url_png($data_url, $abs_path)
{
    if (!$data_url) {
        return false;
    }

    if (strpos($data_url, 'data:image/png;base64,') === 0) {
        $data_url = substr($data_url, strlen('data:image/png;base64,'));
    } elseif (preg_match('~^data:image/\\w+;base64,~', $data_url)) {
        $data_url = preg_replace('~^data:image/\\w+;base64,~', '', $data_url);
    }

    $bin = base64_decode($data_url);
    if ($bin === false) {
        return false;
    }

    return file_put_contents($abs_path, $bin) !== false;
}

function fn_branding_text_resize_png_gd($src_abs, $dst_abs, $w, $h)
{
    if (!$w || !$h) {
        return false;
    }
    if (!function_exists('imagecreatefrompng') || !function_exists('imagecreatetruecolor')) {
        return false;
    }

    $src = @imagecreatefrompng($src_abs);
    if (!$src) {
        return false;
    }

    $dst = imagecreatetruecolor($w, $h);
    if (!$dst) {
        imagedestroy($src);
        return false;
    }

    // preserve alpha
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $w, $h, $transparent);

    $sw = imagesx($src);
    $sh = imagesy($src);

    // contain (keep aspect ratio) + center
    $scale = min($w / max(1, $sw), $h / max(1, $sh));
    $nw = (int) round($sw * $scale);
    $nh = (int) round($sh * $scale);
    $dx = (int) floor(($w - $nw) / 2);
    $dy = (int) floor(($h - $nh) / 2);

    imagecopyresampled($dst, $src, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh);
    $ok = imagepng($dst, $dst_abs);

    imagedestroy($dst);
    imagedestroy($src);

    return (bool) $ok;
}

// Простая проверка доступности контроллера: если нет режима — выведем OK и остановим
if ($mode === 'constructor') {
    $product_id = isset($_REQUEST['product_id']) ? (int) $_REQUEST['product_id'] : 0;
    Tygh::$app['view']->assign('product_id', $product_id);
    Tygh::$app['view']->display('addons/branding_text/views/branding_text/constructor.tpl');
    exit;
} elseif ($mode === 'load') {
    /** @var array $auth */
    global $auth;
    list($company_id, $user_id, $session_id) = fn_branding_text_get_owner_context($auth);
    $company_ids = [$company_id, 0];

    $product_id = isset($_REQUEST['product_id']) ? (int) $_REQUEST['product_id'] : 0;
    $product_type = isset($_REQUEST['product_type']) ? (string) $_REQUEST['product_type'] : '';
    if (!$product_type) {
        $product_type = 'tshirt';
    }

    $type = db_get_row(
        'SELECT * FROM ?:branding_text_product_types WHERE company_id = ?i AND product_type = ?s',
        $company_id,
        $product_type
    );
    if (!$type) {
        $type = db_get_row(
            'SELECT * FROM ?:branding_text_product_types WHERE company_id = ?i AND product_type = ?s',
            0,
            $product_type
        );
    }

    $item = null;
    if ($product_id) {
        if ($user_id > 0) {
            $item = db_get_row(
                'SELECT * FROM ?:branding_text_items WHERE company_id IN (?n) AND product_id = ?i AND (user_id = ?i OR session_id = ?s) ORDER BY updated_at DESC',
                $company_ids,
                $product_id,
                $user_id,
                $session_id
            );
        } else {
            $legacy_session_id = Session::getId();
            $session_ids = array_values(array_unique(array_filter([(string) $session_id, (string) $legacy_session_id])));
            $item = db_get_row(
                'SELECT * FROM ?:branding_text_items WHERE company_id IN (?n) AND product_id = ?i AND session_id IN (?n) ORDER BY updated_at DESC',
                $company_ids,
                $product_id,
                $session_ids
            );
        }
    }

    fn_branding_text_json_response([
        'ok' => true,
        'product_id' => $product_id,
        'product_type' => $product_type,
        'print_area_text' => !empty($type['print_area_text']) ? json_decode($type['print_area_text'], true) : null,
        'print_area_logo' => !empty($type['print_area_logo']) ? json_decode($type['print_area_logo'], true) : null,
        'item' => $item ? [
            'item_id' => (int) $item['item_id'],
            'text_value' => (string) $item['text_value'],
            'text_params' => $item['text_params'] ? json_decode($item['text_params'], true) : null,
            'logo_upload_id' => (int) $item['logo_upload_id'],
            'logo_params' => $item['logo_params'] ? json_decode($item['logo_params'], true) : null,
            'preview_path' => (string) $item['preview_path'],
        ] : null,
    ]);
} elseif ($mode === 'upload_logo') {
    /** @var array $auth */
    global $auth;
    list($company_id, $user_id, $session_id) = fn_branding_text_get_owner_context($auth);

    if (!fn_branding_text_table_exists('branding_text_uploads')) {
        fn_branding_text_json_response(['ok' => false, 'error' => 'DB tables are not installed for branding_text addon'], 500);
    }

    if (empty($_FILES['logo_file']) || !is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
        fn_branding_text_json_response(['ok' => false, 'error' => 'logo_file is required'], 400);
    }

    $f = $_FILES['logo_file'];
    $mime = !empty($f['type']) ? (string) $f['type'] : '';
    if (strpos($mime, 'image/') !== 0) {
        fn_branding_text_json_response(['ok' => false, 'error' => 'Only image uploads are allowed'], 400);
    }

    $files_root = fn_branding_text_get_files_root_dir();
    $rel_original_dir = 'branding_text/uploads/original';
    $rel_preview_dir = 'branding_text/uploads/previews';
    $abs_original_dir = $files_root . '/' . $rel_original_dir;
    $abs_preview_dir = $files_root . '/' . $rel_preview_dir;
    fn_branding_text_ensure_dir($abs_original_dir);
    fn_branding_text_ensure_dir($abs_preview_dir);

    $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
    $ext = $ext ? strtolower($ext) : 'png';
    $base = 'logo_' . ($user_id ? ('u' . $user_id) : ('s' . substr($session_id, 0, 8))) . '_' . time() . '_' . mt_rand(1000, 9999);

    $rel_original = $rel_original_dir . '/' . $base . '.' . $ext;
    $abs_original = $files_root . '/' . $rel_original;
    if (!move_uploaded_file($f['tmp_name'], $abs_original)) {
        fn_branding_text_json_response(['ok' => false, 'error' => 'Failed to save uploaded file'], 500);
    }

    // MVP: preview is the same file (без ресайза). Позже заменим на Sharp/Imagick.
    $rel_preview = $rel_preview_dir . '/' . $base . '.' . $ext;
    $abs_preview = $files_root . '/' . $rel_preview;
    @copy($abs_original, $abs_preview);

    $upload_id = db_query('INSERT INTO ?:branding_text_uploads ?e', [
        'company_id' => $company_id,
        'user_id' => $user_id,
        'session_id' => $session_id,
        'original_filename' => (string) $f['name'],
        'mime_type' => $mime,
        'size' => (int) $f['size'],
        'path_original' => $rel_original,
        'path_preview' => $rel_preview,
        'created_at' => TIME,
    ]);

    fn_branding_text_json_response([
        'ok' => true,
        'upload_id' => (int) $upload_id,
        'path_preview' => $rel_preview,
    ]);
} elseif ($mode === 'list_uploads') {
    /** @var array $auth */
    global $auth;
    list($company_id, $user_id, $session_id) = fn_branding_text_get_owner_context($auth);
    $company_ids = [$company_id, 0];

    if (!fn_branding_text_table_exists('branding_text_uploads')) {
        fn_branding_text_json_response(['ok' => true, 'product_id' => (int) (isset($_REQUEST['product_id']) ? $_REQUEST['product_id'] : 0), 'uploads' => []]);
    }

    $product_id = isset($_REQUEST['product_id']) ? (int) $_REQUEST['product_id'] : 0;

    if ($user_id > 0) {
        $rows = db_get_array(
            'SELECT upload_id, original_filename, mime_type, size, path_preview, created_at'
            . ' FROM ?:branding_text_uploads'
            . ' WHERE company_id IN (?n) AND user_id = ?i'
            . ' ORDER BY created_at DESC'
            . ' LIMIT 50',
            $company_ids,
            $user_id
        );
    } else {
        $legacy_session_id = Session::getId();
        $session_ids = array_values(array_unique(array_filter([(string) $session_id, (string) $legacy_session_id])));
        $rows = db_get_array(
            'SELECT upload_id, original_filename, mime_type, size, path_preview, created_at'
            . ' FROM ?:branding_text_uploads'
            . ' WHERE company_id IN (?n) AND session_id IN (?n)'
            . ' ORDER BY created_at DESC'
            . ' LIMIT 50',
            $company_ids,
            $session_ids
        );
    }

    fn_branding_text_json_response([
        'ok' => true,
        'product_id' => $product_id,
        'uploads' => array_map(function ($r) {
            return [
                'upload_id' => (int) $r['upload_id'],
                'original_filename' => (string) $r['original_filename'],
                'mime_type' => (string) $r['mime_type'],
                'size' => (int) $r['size'],
                'path_preview' => (string) $r['path_preview'],
                'created_at' => (int) $r['created_at'],
            ];
        }, $rows),
    ]);
} elseif ($mode === 'upload_preview') {
    /** @var array $auth */
    global $auth;
    list($company_id, $user_id, $session_id) = fn_branding_text_get_owner_context($auth);
    $company_ids = [$company_id, 0];

    if (!fn_branding_text_table_exists('branding_text_uploads')) {
        http_response_code(404);
        exit;
    }

    $upload_id = isset($_REQUEST['upload_id']) ? (int) $_REQUEST['upload_id'] : 0;
    if (!$upload_id) {
        http_response_code(404);
        exit;
    }

    if ($user_id > 0) {
        $row = db_get_row(
            'SELECT path_preview, mime_type FROM ?:branding_text_uploads'
            . ' WHERE upload_id = ?i AND company_id IN (?n) AND user_id = ?i',
            $upload_id,
            $company_ids,
            $user_id
        );
    } else {
        $legacy_session_id = Session::getId();
        $session_ids = array_values(array_unique(array_filter([(string) $session_id, (string) $legacy_session_id])));
        $row = db_get_row(
            'SELECT path_preview, mime_type FROM ?:branding_text_uploads'
            . ' WHERE upload_id = ?i AND company_id IN (?n) AND session_id IN (?n)',
            $upload_id,
            $company_ids,
            $session_ids
        );
    }
    if (!$row || empty($row['path_preview'])) {
        http_response_code(404);
        exit;
    }

    $files_root = fn_branding_text_get_files_root_dir();
    $abs = $files_root . '/' . ltrim($row['path_preview'], '/');
    if (!is_file($abs)) {
        http_response_code(404);
        exit;
    }

    $mime = !empty($row['mime_type']) ? (string) $row['mime_type'] : 'image/png';
    header('Content-Type: ' . $mime);
    readfile($abs);
    exit;
} elseif ($mode === 'save') {
    /** @var array $auth */
    global $auth;
    list($company_id, $user_id, $session_id) = fn_branding_text_get_owner_context($auth);

    if (!fn_branding_text_table_exists('branding_text_items') || !fn_branding_text_table_exists('branding_text_uploads')) {
        fn_branding_text_json_response(['ok' => false, 'error' => 'DB tables are not installed for branding_text addon'], 500);
    }

    $product_id = isset($_REQUEST['product_id']) ? (int) $_REQUEST['product_id'] : 0;
    if (!$product_id) {
        fn_branding_text_json_response(['ok' => false, 'error' => 'product_id is required'], 400);
    }

    $product_type = isset($_REQUEST['product_type']) ? (string) $_REQUEST['product_type'] : '';
    if (!$product_type) {
        $product_type = 'tshirt';
    }

    $text_value = isset($_REQUEST['text_value']) ? (string) $_REQUEST['text_value'] : '';
    $text_params = isset($_REQUEST['text_params']) ? (string) $_REQUEST['text_params'] : '';
    $logo_params = isset($_REQUEST['logo_params']) ? (string) $_REQUEST['logo_params'] : '';
    $preview_png = isset($_REQUEST['preview_png']) ? (string) $_REQUEST['preview_png'] : '';

    $logo_upload_id = isset($_REQUEST['upload_id']) ? (int) $_REQUEST['upload_id'] : 0;
    if (!$logo_upload_id && !empty($_FILES['logo_file']) && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
        // reuse upload flow
        $_FILES['logo_file']['tmp_name'] = $_FILES['logo_file']['tmp_name'];
        $_REQUEST['__internal_upload'] = 'Y';
        // call same handler by duplicating logic (без редиректа)
        $f = $_FILES['logo_file'];
        $mime = !empty($f['type']) ? (string) $f['type'] : '';
        if (strpos($mime, 'image/') !== 0) {
            fn_branding_text_json_response(['ok' => false, 'error' => 'Only image uploads are allowed'], 400);
        }

        $files_root = fn_branding_text_get_files_root_dir();
        $rel_original_dir = 'branding_text/uploads/original';
        $rel_preview_dir = 'branding_text/uploads/previews';
        $abs_original_dir = $files_root . '/' . $rel_original_dir;
        $abs_preview_dir = $files_root . '/' . $rel_preview_dir;
        fn_branding_text_ensure_dir($abs_original_dir);
        fn_branding_text_ensure_dir($abs_preview_dir);

        $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
        $ext = $ext ? strtolower($ext) : 'png';
        $base = 'logo_' . ($user_id ? ('u' . $user_id) : ('s' . substr($session_id, 0, 8))) . '_' . time() . '_' . mt_rand(1000, 9999);

        $rel_original = $rel_original_dir . '/' . $base . '.' . $ext;
        $abs_original = $files_root . '/' . $rel_original;
        if (!move_uploaded_file($f['tmp_name'], $abs_original)) {
            fn_branding_text_json_response(['ok' => false, 'error' => 'Failed to save uploaded file'], 500);
        }

        $rel_preview = $rel_preview_dir . '/' . $base . '.' . $ext;
        $abs_preview = $files_root . '/' . $rel_preview;
        @copy($abs_original, $abs_preview);

        $logo_upload_id = (int) db_query('INSERT INTO ?:branding_text_uploads ?e', [
            'company_id' => $company_id,
            'user_id' => $user_id,
            'session_id' => $session_id,
            'original_filename' => (string) $f['name'],
            'mime_type' => $mime,
            'size' => (int) $f['size'],
            'path_original' => $rel_original,
            'path_preview' => $rel_preview,
            'created_at' => TIME,
        ]);
    }

    $files_root = fn_branding_text_get_files_root_dir();
    $rel_preview_dir = 'branding_text/previews';
    $abs_preview_dir = $files_root . '/' . $rel_preview_dir;
    fn_branding_text_ensure_dir($abs_preview_dir);

    // Stable per-owner preview path: overwrite on each save.
    $preview_rel_path = '';
    if ($preview_png) {
        $owner_key = $user_id ? ('u' . $user_id) : ('s' . substr($session_id, 0, 16));
        $base = 'preview_' . $owner_key . '_p' . $product_id;
        $preview_rel_path = $rel_preview_dir . '/' . $base . '.png';
        $preview_abs_path = $files_root . '/' . $preview_rel_path;
        if (!fn_branding_text_save_data_url_png($preview_png, $preview_abs_path)) {
            fn_branding_text_json_response(['ok' => false, 'error' => 'Failed to save preview'], 500);
        }
    }

    $existing_item_id = (int) db_get_field(
        'SELECT item_id FROM ?:branding_text_items WHERE company_id = ?i AND product_id = ?i AND ((user_id = ?i AND ?i <> 0) OR (session_id = ?s AND ?i = 0))',
        $company_id,
        $product_id,
        $user_id,
        $user_id,
        $session_id,
        $user_id
    );

    $data = [
        'company_id' => $company_id,
        'user_id' => $user_id,
        'session_id' => $session_id,
        'product_id' => $product_id,
        'product_type' => $product_type,
        'text_value' => $text_value,
        'text_params' => $text_params,
        'logo_upload_id' => $logo_upload_id,
        'logo_params' => $logo_params,
        'preview_path' => $preview_rel_path,
        'updated_at' => TIME,
    ];

    if ($existing_item_id) {
        // если превью не пришло — не затираем старый путь
        if (!$preview_rel_path) {
            unset($data['preview_path']);
        }
        db_query('UPDATE ?:branding_text_items SET ?u WHERE item_id = ?i', $data, $existing_item_id);
        $item_id = $existing_item_id;
    } else {
        $data['created_at'] = TIME;
        $item_id = (int) db_query('INSERT INTO ?:branding_text_items ?e', $data);
    }

    if (isset(\Tygh\Tygh::$app['session'])) {
        \Tygh\Tygh::$app['session']['bt_cache_bust'] = TIME;
    }

    fn_branding_text_json_response([
        'ok' => true,
        'item_id' => (int) $item_id,
        'preview_path' => $preview_rel_path,
        'logo_upload_id' => (int) $logo_upload_id,
    ]);
} elseif ($mode === 'auto_apply') {
    /** @var array $auth */
    global $auth;
    list($company_id, $user_id, $session_id) = fn_branding_text_get_owner_context($auth);

    $brand_text = isset($_REQUEST['brand_text']) ? (string) $_REQUEST['brand_text'] : '';
    $logo_upload_id = isset($_REQUEST['upload_id']) ? (int) $_REQUEST['upload_id'] : 0;
    $product_ids = isset($_REQUEST['product_ids']) ? (array) $_REQUEST['product_ids'] : [];
    $product_type = isset($_REQUEST['product_type']) ? (string) $_REQUEST['product_type'] : 'tshirt';

    if (!$brand_text) {
        fn_branding_text_json_response(['ok' => false, 'error' => 'brand_text is required'], 400);
    }

    if (empty($product_ids)) {
        fn_branding_text_json_response(['ok' => false, 'error' => 'product_ids is required'], 400);
    }

    $type = db_get_row(
        'SELECT * FROM ?:branding_text_product_types WHERE company_id = ?i AND product_type = ?s',
        $company_id,
        $product_type
    );
    if (!$type) {
        $type = db_get_row(
            'SELECT * FROM ?:branding_text_product_types WHERE company_id = ?i AND product_type = ?s',
            0,
            $product_type
        );
    }

    $area_text = !empty($type['print_area_text']) ? json_decode($type['print_area_text'], true) : null;
    $area_logo = !empty($type['print_area_logo']) ? json_decode($type['print_area_logo'], true) : null;

    $applied = [];
    foreach ($product_ids as $pid) {
        $pid = (int) $pid;
        if (!$pid) {
            continue;
        }

        $text_params = $area_text ? json_encode([
            'x' => $area_text['x'] + $area_text['w'] / 2,
            'y' => $area_text['y'] + $area_text['h'] / 2,
            'align' => 'center',
        ], JSON_UNESCAPED_UNICODE) : '';

        $logo_params = $area_logo ? json_encode([
            'x' => $area_logo['x'] + $area_logo['w'] / 2,
            'y' => $area_logo['y'] + $area_logo['h'] / 2,
            'scale' => 1,
            'rotation' => 0,
        ], JSON_UNESCAPED_UNICODE) : '';

        $existing_item_id = (int) db_get_field(
            'SELECT item_id FROM ?:branding_text_items WHERE company_id = ?i AND product_id = ?i AND ((user_id = ?i AND ?i <> 0) OR (session_id = ?s AND ?i = 0))',
            $company_id,
            $pid,
            $user_id,
            $user_id,
            $session_id,
            $user_id
        );

        $data = [
            'company_id' => $company_id,
            'user_id' => $user_id,
            'session_id' => $session_id,
            'product_id' => $pid,
            'product_type' => $product_type,
            'text_value' => $brand_text,
            'text_params' => $text_params,
            'logo_upload_id' => $logo_upload_id,
            'logo_params' => $logo_params,
            'updated_at' => TIME,
        ];

        if ($existing_item_id) {
            db_query('UPDATE ?:branding_text_items SET ?u WHERE item_id = ?i', $data, $existing_item_id);
            $applied[] = (int) $existing_item_id;
        } else {
            $data['created_at'] = TIME;
            $applied[] = (int) db_query('INSERT INTO ?:branding_text_items ?e', $data);
        }
    }

    fn_branding_text_json_response([
        'ok' => true,
        'applied_item_ids' => $applied,
    ]);
} elseif ($mode === 'preview') {
    $item_id = isset($_REQUEST['item_id']) ? (int) $_REQUEST['item_id'] : 0;
    if (!$item_id) {
        http_response_code(404);
        exit;
    }

    $preview = db_get_field('SELECT preview_path FROM ?:branding_text_items WHERE item_id = ?i', $item_id);
    if (!$preview) {
        http_response_code(404);
        exit;
    }

    $files_root = fn_branding_text_get_files_root_dir();
    $abs = $files_root . '/' . ltrim($preview, '/');
    if (!is_file($abs)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: image/png');
    readfile($abs);
    exit;
} elseif ($mode === 'preview_for_product') {
    /** @var array $auth */
    global $auth;

    if (!is_array($auth)) {
        $auth = [];
    }

    list($company_id, $user_id, $session_id) = fn_branding_text_get_owner_context($auth);
    $company_ids = [$company_id, 0];

    $product_id = isset($_REQUEST['product_id']) ? (int) $_REQUEST['product_id'] : 0;
    if (!$product_id) {
        http_response_code(404);
        exit;
    }

    if (!fn_branding_text_table_exists('branding_text_items')) {
        fn_branding_text_debug_log('preview_for_product_404_no_table', [
            'product_id' => $product_id,
        ]);
        http_response_code(404);
        exit;
    }

    $w = isset($_REQUEST['w']) ? (int) $_REQUEST['w'] : 0;
    $h = isset($_REQUEST['h']) ? (int) $_REQUEST['h'] : 0;
    if ($w < 0) { $w = 0; }
    if ($h < 0) { $h = 0; }
    if ($w > 2000) { $w = 2000; }
    if ($h > 2000) { $h = 2000; }

    if ($user_id > 0) {
        $preview = db_get_field(
            'SELECT preview_path FROM ?:branding_text_items'
            . ' WHERE company_id IN (?n) AND product_id = ?i'
            . ' AND (user_id = ?i OR session_id = ?s)'
            . ' ORDER BY updated_at DESC',
            $company_ids,
            $product_id,
            $user_id,
            $session_id
        );
    } else {
        $legacy_session_id = Session::getId();
        $session_ids = array_values(array_unique(array_filter([(string) $session_id, (string) $legacy_session_id])));
        $preview = db_get_field(
            'SELECT preview_path FROM ?:branding_text_items'
            . ' WHERE company_id IN (?n) AND product_id = ?i'
            . ' AND session_id IN (?n)'
            . ' ORDER BY updated_at DESC',
            $company_ids,
            $product_id,
            $session_ids
        );
    }
    if (!$preview) {
        fn_branding_text_debug_log('preview_for_product_404_no_preview', [
            'product_id' => $product_id,
            'company_id' => $company_id,
            'user_id' => $user_id,
            'session_id' => substr((string) $session_id, 0, 8),
        ]);
        http_response_code(404);
        exit;
    }

    $files_root = fn_branding_text_get_files_root_dir();
    $abs = $files_root . '/' . ltrim($preview, '/');
    if (!is_file($abs)) {
        fn_branding_text_debug_log('preview_for_product_404_no_file', [
            'product_id' => $product_id,
            'preview_path' => $preview,
            'abs' => $abs,
        ]);
        http_response_code(404);
        exit;
    }

    // If size requested, generate cached resized version (PNG).
    if ($w && $h) {
        $rel_cache_dir = 'branding_text/previews_cache';
        $abs_cache_dir = $files_root . '/' . $rel_cache_dir;
        fn_branding_text_ensure_dir($abs_cache_dir);

        $mtime = @filemtime($abs);
        $cache_key = md5($preview . '|' . (string) $mtime . '|' . $w . 'x' . $h);
        $cache_rel = $rel_cache_dir . '/' . $cache_key . '_' . $w . 'x' . $h . '.png';
        $cache_abs = $files_root . '/' . $cache_rel;

        if (!is_file($cache_abs)) {
            // best-effort cache build
            fn_branding_text_resize_png_gd($abs, $cache_abs, $w, $h);
        }

        if (is_file($cache_abs)) {
            header('Content-Type: image/png');
            readfile($cache_abs);
            exit;
        }
        // fallback to original
    }

    header('Content-Type: image/png');
    readfile($abs);
    exit;
} elseif ($mode === 'ping') {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'branding_text controller OK';
    exit;
} elseif ($mode === 'debug_ping') {
    header('Content-Type: application/json; charset=utf-8');

    $var_dir = Registry::get('config.dir.var');
    $var_dir = rtrim((string) $var_dir, '/\\');
    $file = $var_dir . '/branding_text_debug.log';

    $dir_ok = is_dir($var_dir);
    $dir_writable = $dir_ok ? is_writable($var_dir) : false;
    $written = false;
    $err = '';

    try {
        $payload = json_encode([
            'ts' => date('c'),
            'mode' => 'debug_ping',
        ], JSON_UNESCAPED_UNICODE) . "\n";
        $written = @file_put_contents($file, $payload, FILE_APPEND) !== false;
    } catch (Exception $e) {
        $err = $e->getMessage();
    }

    echo json_encode([
        'ok' => true,
        'var_dir' => $var_dir,
        'var_dir_exists' => $dir_ok,
        'var_dir_writable' => $dir_writable,
        'log_file' => $file,
        'log_file_exists' => is_file($file),
        'log_file_writable' => is_file($file) ? is_writable($file) : null,
        'write_attempt_ok' => $written,
        'last_error' => $err,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
