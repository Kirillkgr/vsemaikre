<?php

use Tygh\Enum\SiteArea;
use Tygh\Enum\StorefrontStatuses;
use Tygh\Enum\VendorStatuses;
use Tygh\Enum\UserTypes;
use Tygh\Enum\YesNo;
use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

/** @var array $auth */

$buy_session = &Tygh::$app['session']['brending_wizart_buy'];
$buy_session = is_array($buy_session) ? $buy_session : [];

$wizard_session = &Tygh::$app['session']['brending_wizart'];
$wizard_session = is_array($wizard_session) ? $wizard_session : [];

if (empty($auth['user_id'])) {
    if (!in_array($mode, ['buy', 'buy_save', 'wizard', 'save'], true)) {
        $return_url = urlencode('brending_wizart.wizard');
        return [CONTROLLER_STATUS_REDIRECT, 'auth.login_form?return_url=' . $return_url];
    }
}

if ($mode === 'buy_save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data_source = !empty($wizard_session) ? $wizard_session : $buy_session;
    $storefront = isset($_REQUEST['storefront']) && is_array($_REQUEST['storefront']) ? $_REQUEST['storefront'] : (isset($data_source['storefront']) ? $data_source['storefront'] : []);
    $user = isset($_REQUEST['user']) && is_array($_REQUEST['user']) ? $_REQUEST['user'] : (isset($data_source['user']) ? $data_source['user'] : []);

    if (!empty($wizard_session)) {
        $password1 = isset($wizard_session['password1']) ? (string) $wizard_session['password1'] : '';
        $password2 = isset($wizard_session['password2']) ? (string) $wizard_session['password2'] : '';
    } else {
        $password1 = isset($user['password1']) ? (string) $user['password1'] : '';
        $password2 = isset($user['password2']) ? (string) $user['password2'] : '';
    }

    $buy_session['storefront'] = [
        'vendor_nick' => isset($storefront['vendor_nick']) ? trim((string) $storefront['vendor_nick']) : '',
        'name'        => isset($storefront['name']) ? trim((string) $storefront['name']) : '',
    ];
    $buy_session['user'] = [
        'email'     => isset($user['email']) ? trim((string) $user['email']) : '',
        'firstname' => isset($user['firstname']) ? trim((string) $user['firstname']) : '',
        'lastname'  => isset($user['lastname']) ? trim((string) $user['lastname']) : '',
        'phone'     => isset($user['phone']) ? trim((string) $user['phone']) : '',
    ];

    if (!empty($wizard_session)) {
        $wizard_session['storefront'] = $buy_session['storefront'];
        $wizard_session['user'] = $buy_session['user'];
        $wizard_session['role'] = isset($wizard_session['role']) ? (string) $wizard_session['role'] : 'streamer';
        $wizard_session['preset'] = isset($wizard_session['preset']) ? (string) $wizard_session['preset'] : 'light';
    }

    if ($password1 === '' || $password2 === '' || $password1 !== $password2) {
        fn_set_notification('E', __('error'), 'Пароли не совпадают');
        return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.buy'];
    }

    $vendor_nick = isset($buy_session['storefront']['vendor_nick']) ? trim((string) $buy_session['storefront']['vendor_nick']) : '';
    $vendor_nick = mb_strtolower($vendor_nick);
    $vendor_nick = preg_replace('/\s+/u', '-', $vendor_nick);
    $vendor_nick = preg_replace('/[^a-z0-9\-]+/u', '', $vendor_nick);
    $vendor_nick = trim((string) $vendor_nick, '-');

    if ($vendor_nick === '') {
        fn_set_notification('E', __('error'), 'Укажите ник (латиницей)');
        return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.buy'];
    }

    $email = isset($buy_session['user']['email']) ? (string) $buy_session['user']['email'] : '';
    $storefront_name = isset($buy_session['storefront']['name']) ? (string) $buy_session['storefront']['name'] : '';
    $firstname = isset($buy_session['user']['firstname']) ? (string) $buy_session['user']['firstname'] : '';
    $lastname = isset($buy_session['user']['lastname']) ? (string) $buy_session['user']['lastname'] : '';
    $phone = isset($buy_session['user']['phone']) ? (string) $buy_session['user']['phone'] : '';

    $role = isset($wizard_session['role']) ? (string) $wizard_session['role'] : 'streamer';
    $preset = isset($wizard_session['preset']) ? (string) $wizard_session['preset'] : 'light';
    $background_color = isset($wizard_session['background_color']) ? (string) $wizard_session['background_color'] : '';
    $accent_color = isset($wizard_session['accent_color']) ? (string) $wizard_session['accent_color'] : '';
    $text_color = isset($wizard_session['text_color']) ? (string) $wizard_session['text_color'] : '';

    $company_data = [
        'company' => $storefront_name,
        'email' => $email,
        'phone' => $phone,
        'timestamp' => TIME,
        'status' => VendorStatuses::ACTIVE,
        'admin_firstname' => $firstname,
        'admin_lastname' => $lastname,
        'lang_code' => CART_LANGUAGE,
        'company_description' => $role,
    ];

    $company_id = fn_update_company($company_data);
    if (!$company_id) {
        return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.buy'];
    }

    $null = [];
    $user_data = [
        'create_vendor_admin' => YesNo::YES,
        'company_id' => (int) $company_id,
        'user_type' => UserTypes::VENDOR,
        'email' => $email,
        'user_login' => $email,
        'password1' => $password1,
        'password2' => $password2,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'phone' => $phone,
        'status' => 'A',
    ];

    $created = fn_update_user(0, $user_data, $null, false, false);
    if (!$created) {
        return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.buy'];
    }
    $created_user_id = (int) $created[0];
    if (!$created_user_id) {
        return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.buy'];
    }

    $ekey = fn_generate_ekey($created_user_id, RECOVERY_PASSWORD_EKEY_TYPE, RECOVERY_PASSWORD_TTL);

    $http_host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : 'localhost';
    $host_parts = explode(':', $http_host);
    $host_without_port = (string) reset($host_parts);
    $port = count($host_parts) > 1 ? (int) end($host_parts) : null;

    $base_domain = $host_without_port;
    if (substr($host_without_port, -strlen('.localhost')) === '.localhost') {
        $base_domain = 'localhost';
    } else {
        $domain_parts = array_values(array_filter(explode('.', $host_without_port), static function ($p) {
            return $p !== '';
        }));
        if (count($domain_parts) >= 2) {
            $base_domain = implode('.', array_slice($domain_parts, -2));
        }
    }

    /** @var \Tygh\Storefront\Repository $repository */
    $repository = Tygh::$app['storefront.repository'];
    /** @var \Tygh\Storefront\Factory $factory */
    $factory = Tygh::$app['storefront.factory'];

    $default_storefront = $repository->findDefault();
    $default_theme = $default_storefront ? (string) $default_storefront->theme_name : '';
    $copy_layouts_from_storefront_id = $default_storefront ? (int) $default_storefront->storefront_id : null;
    $language_ids = $default_storefront ? (array) $default_storefront->getLanguageIds() : [];
    $currency_ids = $default_storefront ? (array) $default_storefront->getCurrencyIds() : [];

    $candidate_nick = $vendor_nick;
    $storefront_url = '';
    $existing = null;

    for ($i = 0; $i < 20; $i++) {
        $storefront_url = $candidate_nick . '.' . $base_domain;
        if ($port) {
            $storefront_url .= ':' . $port;
        }
        $existing = $repository->findByUrl($storefront_url);
        if (!$existing) {
            break;
        }
        $candidate_nick = $vendor_nick . '-' . ($i + 2);
    }

    if ($existing) {
        fn_set_notification('E', __('error'), 'Не удалось подобрать свободный субдомен для витрины');
        return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.buy'];
    }

    $storefront_data = [
        'url' => $storefront_url,
        'name' => $storefront_name ? $storefront_name : ('Store ' . $candidate_nick),
        'status' => StorefrontStatuses::OPEN,
        'theme_name' => $default_theme,
        'company_ids' => [(int) $company_id],
        'language_ids' => $language_ids,
        'currency_ids' => $currency_ids,
        'extra' => [
            'copy_layouts_from_storefront_id' => $copy_layouts_from_storefront_id,
            'brending_wizart' => [
                'preset' => $preset,
                'background_color' => $background_color,
                'accent_color' => $accent_color,
                'text_color' => $text_color,
            ],
        ],
    ];

    $new_storefront = $factory->fromArray($storefront_data);
    $save_result = $repository->save($new_storefront);
    $save_result->showNotifications(true);

    if (!$save_result->isSuccess()) {
        return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.buy'];
    }

    Tygh::$app['db']->query(
        'CREATE TABLE IF NOT EXISTS ?:brending_wizart_storefront_settings ('
        . ' storefront_id int(11) unsigned NOT NULL,'
        . ' background_color varchar(16) NOT NULL DEFAULT \'\','
        . ' accent_color varchar(16) NOT NULL DEFAULT \'\','
        . ' text_color varchar(16) NOT NULL DEFAULT \'\','
        . ' updated_at int(11) unsigned NOT NULL DEFAULT 0,'
        . ' PRIMARY KEY (storefront_id)'
        . ') ENGINE=InnoDB DEFAULT CHARSET=UTF8'
    );

    Tygh::$app['db']->replaceInto('brending_wizart_storefront_settings', [
        'storefront_id' => (int) $new_storefront->storefront_id,
        'background_color' => $background_color,
        'accent_color' => $accent_color,
        'text_color' => $text_color,
        'updated_at' => time(),
    ]);

    $created_storefront_id = (int) $new_storefront->storefront_id;

    $files_dir = rtrim((string) Registry::get('config.dir.files'), '/\\');
    fn_mkdir($files_dir);

    if (!empty($_FILES['bw_logo_header']) && !empty($_FILES['bw_logo_header']['tmp_name']) && is_uploaded_file($_FILES['bw_logo_header']['tmp_name'])) {
        $tmp = (string) $_FILES['bw_logo_header']['tmp_name'];
        $ext = isset($_FILES['bw_logo_header']['name']) ? strtolower((string) pathinfo((string) $_FILES['bw_logo_header']['name'], PATHINFO_EXTENSION)) : '';
        $ext = $ext !== '' ? '.' . $ext : '';

        $tmp_copy = $files_dir . '/bw_logo_' . uniqid('', true) . '_header' . $ext;
        $copy_ok = @copy($tmp, $tmp_copy);
        @chmod($tmp_copy, 0644);
        $copy_ok = $copy_ok && @filesize($tmp_copy) > 0 && is_readable($tmp_copy);

        if ($copy_ok) {
            $resized = fn_resize_image($tmp_copy, 320, 120, '');
            if ($resized !== false) {
                list($contents, $format) = $resized;
                $resized_path = $files_dir . '/bw_logo_' . uniqid('', true) . '_header_resized.' . $format;
                $write_ok = fn_put_contents($resized_path, $contents);
                @chmod($resized_path, 0644);
                if ($write_ok && @filesize($resized_path) > 0 && is_readable($resized_path)) {
                    @unlink($tmp_copy);
                    $tmp_copy = $resized_path;
                    $ext = '.' . $format;
                } else {
                    @unlink($resized_path);
                }
            }

            $icon = [[
                'path' => $tmp_copy,
                'name' => 'logo' . $ext,
                'size' => (int) filesize($tmp_copy),
            ]];

            $logo_id_storefront_theme = fn_update_logo([
                'type'      => 'theme',
                'layout_id' => 0,
                'style_id'  => '',
            ], 0, $created_storefront_id);

            if ($logo_id_storefront_theme) {
                fn_delete_image_pairs((int) $logo_id_storefront_theme, 'logos', 'M');
                fn_update_image_pairs(
                    $icon,
                    [],
                    [[
                        'type'      => 'M',
                        'object_id' => (int) $logo_id_storefront_theme,
                        'is_new'    => 'Y',
                        'image_alt' => $storefront_name,
                    ]],
                    0,
                    'logos'
                );
            }
        } else {
            fn_set_notification('E', __('error'), 'Не удалось подготовить файл логотипа для загрузки');
        }

        @unlink($tmp_copy);
    }

    if (!empty($_FILES['bw_logo_list']) && !empty($_FILES['bw_logo_list']['tmp_name']) && is_uploaded_file($_FILES['bw_logo_list']['tmp_name'])) {
        $tmp = (string) $_FILES['bw_logo_list']['tmp_name'];
        $ext = isset($_FILES['bw_logo_list']['name']) ? strtolower((string) pathinfo((string) $_FILES['bw_logo_list']['name'], PATHINFO_EXTENSION)) : '';
        $ext = $ext !== '' ? '.' . $ext : '';

        $tmp_copy_1 = $files_dir . '/bw_logo_' . uniqid('', true) . '_list_1' . $ext;
        $tmp_copy_2 = $files_dir . '/bw_logo_' . uniqid('', true) . '_list_2' . $ext;

        $copy_ok = true;
        $copy_ok = @copy($tmp, $tmp_copy_1) && $copy_ok;
        $copy_ok = @copy($tmp, $tmp_copy_2) && $copy_ok;
        @chmod($tmp_copy_1, 0644);
        @chmod($tmp_copy_2, 0644);
        $copy_ok = $copy_ok
            && @filesize($tmp_copy_1) > 0
            && @filesize($tmp_copy_2) > 0
            && is_readable($tmp_copy_1)
            && is_readable($tmp_copy_2);

        if ($copy_ok) {
            $resized_1 = fn_resize_image($tmp_copy_1, 200, 200, '');
            if ($resized_1 !== false) {
                list($contents, $format) = $resized_1;
                $resized_path = $files_dir . '/bw_logo_' . uniqid('', true) . '_list_1_resized.' . $format;
                $write_ok = fn_put_contents($resized_path, $contents);
                @chmod($resized_path, 0644);
                if ($write_ok && @filesize($resized_path) > 0 && is_readable($resized_path)) {
                    @unlink($tmp_copy_1);
                    $tmp_copy_1 = $resized_path;
                    $ext = '.' . $format;
                } else {
                    @unlink($resized_path);
                }
            }

            $resized_2 = fn_resize_image($tmp_copy_2, 200, 200, '');
            if ($resized_2 !== false) {
                list($contents, $format) = $resized_2;
                $resized_path = $files_dir . '/bw_logo_' . uniqid('', true) . '_list_2_resized.' . $format;
                $write_ok = fn_put_contents($resized_path, $contents);
                @chmod($resized_path, 0644);
                if ($write_ok && @filesize($resized_path) > 0 && is_readable($resized_path)) {
                    @unlink($tmp_copy_2);
                    $tmp_copy_2 = $resized_path;
                    $ext = '.' . $format;
                } else {
                    @unlink($resized_path);
                }
            }

            $icon_1 = [[
                'path' => $tmp_copy_1,
                'name' => 'logo' . $ext,
                'size' => (int) filesize($tmp_copy_1),
            ]];
            $icon_2 = [[
                'path' => $tmp_copy_2,
                'name' => 'logo' . $ext,
                'size' => (int) filesize($tmp_copy_2),
            ]];

            $logo_id_company_theme = fn_update_logo([
                'type'      => 'theme',
                'layout_id' => 0,
                'style_id'  => '',
            ], (int) $company_id, $created_storefront_id);

            if ($logo_id_company_theme) {
                fn_delete_image_pairs((int) $logo_id_company_theme, 'logos', 'M');
                fn_update_image_pairs(
                    $icon_1,
                    [],
                    [[
                        'type'      => 'M',
                        'object_id' => (int) $logo_id_company_theme,
                        'is_new'    => 'Y',
                        'image_alt' => $storefront_name,
                    ]],
                    0,
                    'logos'
                );
            }

            $logo_id_company_vendor = fn_update_logo([
                'type'      => 'vendor',
                'layout_id' => 0,
                'style_id'  => '',
            ], (int) $company_id, $created_storefront_id);

            if ($logo_id_company_vendor) {
                fn_delete_image_pairs((int) $logo_id_company_vendor, 'logos', 'M');
                fn_update_image_pairs(
                    $icon_2,
                    [],
                    [[
                        'type'      => 'M',
                        'object_id' => (int) $logo_id_company_vendor,
                        'is_new'    => 'Y',
                        'image_alt' => $storefront_name,
                    ]],
                    0,
                    'logos'
                );
            }
        } else {
            fn_set_notification('E', __('error'), 'Не удалось подготовить файл логотипа для загрузки');
        }

        @unlink($tmp_copy_1);
        @unlink($tmp_copy_2);
    }

    Tygh::$app['session']['brending_wizart_vendor_autostart_done'] = true;

    $redirect_url = fn_url('products.manage', SiteArea::VENDOR_PANEL);
    $target_url = fn_url('auth.ekey_login?ekey=' . $ekey . '&redirect_url=' . urlencode($redirect_url), SiteArea::VENDOR_PANEL);
    return [CONTROLLER_STATUS_REDIRECT, $target_url];
}

if ($mode === 'buy') {
    return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.wizard?step=1'];
}

$step = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : 1;
if ($step < 1) {
    $step = 1;
}
if ($step > 3) {
    $step = 3;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'save') {
        $data = isset($_REQUEST['wizard']) && is_array($_REQUEST['wizard']) ? $_REQUEST['wizard'] : [];

        if ($step === 1) {
            $wizard_session['storefront'] = [
                'vendor_nick' => isset($data['vendor_nick']) ? trim((string) $data['vendor_nick']) : '',
                'name'        => isset($data['storefront_name']) ? trim((string) $data['storefront_name']) : '',
            ];
            $wizard_session['user'] = [
                'email'     => isset($data['email']) ? trim((string) $data['email']) : '',
                'firstname' => isset($data['firstname']) ? trim((string) $data['firstname']) : '',
                'lastname'  => isset($data['lastname']) ? trim((string) $data['lastname']) : '',
                'phone'     => isset($data['phone']) ? trim((string) $data['phone']) : '',
            ];

            $wizard_session['password1'] = isset($data['password1']) ? (string) $data['password1'] : '';
            $wizard_session['password2'] = isset($data['password2']) ? (string) $data['password2'] : '';
            $wizard_session['role'] = isset($data['role']) ? (string) $data['role'] : 'streamer';

            return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.wizard?step=2'];
        }

        if ($step === 2) {
            $preset = isset($data['preset']) ? (string) $data['preset'] : 'light';
            if (!in_array($preset, ['light', 'dark', 'bright'], true)) {
                $preset = 'light';
            }
            $wizard_session['preset'] = $preset;

            $wizard_session['background_color'] = isset($data['background_color']) ? (string) $data['background_color'] : '';
            $wizard_session['accent_color'] = isset($data['accent_color']) ? (string) $data['accent_color'] : '';
            $wizard_session['text_color'] = isset($data['text_color']) ? (string) $data['text_color'] : '';

            return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.wizard?step=3'];
        }
    }

    return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.wizard?step=' . $step];
}

Tygh::$app['view']->assign([
    'bw_step' => $step,
    'bw_data' => $wizard_session,
]);

if ($mode === 'buy') {
    Tygh::$app['view']->assign([
        'bw_buy_data' => $buy_session,
    ]);
}

if ($mode === 'wizard') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $step === 1) {
        fn_set_notification('N', __('notice'), 'Запущен мастер настройки');
    }
    fn_add_breadcrumb(__('home'), 'index.index');
    fn_add_breadcrumb('Мастер настройки магазина');
}

if ($mode === 'buy') {
    fn_add_breadcrumb(__('home'), 'index.index');
    fn_add_breadcrumb('Купить магазин');
}
