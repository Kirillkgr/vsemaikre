<?php

use Tygh\Enum\NotificationSeverity;
use Tygh\Enum\SiteArea;
use Tygh\Enum\StorefrontStatuses;
use Tygh\Enum\UserTypes;
use Tygh\Registry;
use Tygh\Tools\Url;

defined('BOOTSTRAP') or die('Access denied');

/** @var string $mode */
/** @var array $auth */

if (empty($auth['user_type']) || !UserTypes::isVendor($auth['user_type'])) {
    return [CONTROLLER_STATUS_DENIED];
}

/** @var \Tygh\Storefront\Repository $repository */
$repository = Tygh::$app['storefront.repository'];
$vendor_storefront = $repository->findAvailableForCompanyId((int) $auth['company_id']);

if ($vendor_storefront) {
    $vendor_storefront = $repository->findById((int) $vendor_storefront->storefront_id);
}

$wizard_session = &Tygh::$app['session']['brending_wizart_vendor'];
$wizard_session = is_array($wizard_session) ? $wizard_session : [];

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

if ($vendor_storefront) {
    $settings = Tygh::$app['db']->getRow(
        'SELECT background_color, accent_color, text_color FROM ?:brending_wizart_storefront_settings WHERE storefront_id = ?i',
        (int) $vendor_storefront->storefront_id
    );
    if (is_array($settings) && $settings) {
        if (isset($settings['background_color'])) {
            $wizard_session['background_color'] = (string) $settings['background_color'];
        }
        if (isset($settings['accent_color'])) {
            $wizard_session['accent_color'] = (string) $settings['accent_color'];
        }
        if (isset($settings['text_color'])) {
            $wizard_session['text_color'] = (string) $settings['text_color'];
        }
    }
}

$bw_storefront_url = '';
if ($vendor_storefront) {
    $current_location = (string) Registry::get('config.current_location');
    $current_url = new Url($current_location);
    $storefront_url = new Url('http://' . trim((string) $vendor_storefront->url));

    $storefront_url->setProtocol($current_url->getProtocol() ?: $storefront_url->getProtocol());
    if ($current_url->getPort() !== null) {
        $storefront_url->setPort($current_url->getPort());
    }
    $storefront_url->setPath('/');
    $storefront_url->setQueryParams([
        'bw_preview' => time(),
    ]);

    $bw_storefront_url = $storefront_url->build(false, false);
}

if ($mode === 'save_my_store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$vendor_storefront) {
        fn_set_notification(NotificationSeverity::ERROR, __('error'), 'Витрина продавца не найдена');
        return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.my_store'];
    }

    $data = isset($_REQUEST['wizard']) && is_array($_REQUEST['wizard']) ? $_REQUEST['wizard'] : [];

    $background_color = isset($data['background_color_text']) && trim((string) $data['background_color_text']) !== ''
        ? trim((string) $data['background_color_text'])
        : (isset($data['background_color']) ? (string) $data['background_color'] : '');
    $accent_color = isset($data['accent_color_text']) && trim((string) $data['accent_color_text']) !== ''
        ? trim((string) $data['accent_color_text'])
        : (isset($data['accent_color']) ? (string) $data['accent_color'] : '');
    $text_color = isset($data['text_color_text']) && trim((string) $data['text_color_text']) !== ''
        ? trim((string) $data['text_color_text'])
        : (isset($data['text_color']) ? (string) $data['text_color'] : '');

    $wizard_session['background_color'] = $background_color;
    $wizard_session['accent_color'] = $accent_color;
    $wizard_session['text_color'] = $text_color;

    Tygh::$app['db']->replaceInto('brending_wizart_storefront_settings', [
        'storefront_id' => (int) $vendor_storefront->storefront_id,
        'background_color' => $background_color,
        'accent_color' => $accent_color,
        'text_color' => $text_color,
        'updated_at' => time(),
    ]);

    $files_dir = rtrim((string) Registry::get('config.dir.files'), '/\\');
    fn_mkdir($files_dir);
    $image_alt = isset($vendor_storefront->name) ? (string) $vendor_storefront->name : '';

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
            ], 0, (int) $vendor_storefront->storefront_id);

            if ($logo_id_storefront_theme) {
                fn_delete_image_pairs((int) $logo_id_storefront_theme, 'logos', 'M');
                fn_update_image_pairs(
                    $icon,
                    [],
                    [[
                        'type'      => 'M',
                        'object_id' => (int) $logo_id_storefront_theme,
                        'is_new'    => 'Y',
                        'image_alt' => $image_alt,
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
            ], (int) $auth['company_id'], (int) $vendor_storefront->storefront_id);

            if ($logo_id_company_theme) {
                fn_delete_image_pairs((int) $logo_id_company_theme, 'logos', 'M');
                fn_update_image_pairs(
                    $icon_1,
                    [],
                    [[
                        'type'      => 'M',
                        'object_id' => (int) $logo_id_company_theme,
                        'is_new'    => 'Y',
                        'image_alt' => $image_alt,
                    ]],
                    0,
                    'logos'
                );
            }

            $logo_id_company_vendor = fn_update_logo([
                'type'      => 'vendor',
                'layout_id' => 0,
                'style_id'  => '',
            ], (int) $auth['company_id'], (int) $vendor_storefront->storefront_id);

            if ($logo_id_company_vendor) {
                fn_delete_image_pairs((int) $logo_id_company_vendor, 'logos', 'M');
                fn_update_image_pairs(
                    $icon_2,
                    [],
                    [[
                        'type'      => 'M',
                        'object_id' => (int) $logo_id_company_vendor,
                        'is_new'    => 'Y',
                        'image_alt' => $image_alt,
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

    fn_set_notification('N', __('notice'), 'Настройки сохранены');
    return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.my_store'];
}

$step = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : 1;
if ($step < 1) {
    $step = 1;
}
if ($step > 4) {
    $step = 4;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($mode === 'save') {
        $data = isset($_REQUEST['wizard']) && is_array($_REQUEST['wizard']) ? $_REQUEST['wizard'] : [];

        if ($step === 1) {
            $store_name = isset($data['store_name']) ? trim((string) $data['store_name']) : '';
            $store_description = isset($data['store_description']) ? trim((string) $data['store_description']) : '';
            $vendor_nick = isset($data['vendor_nick']) ? trim((string) $data['vendor_nick']) : '';

            $vendor_nick = mb_strtolower($vendor_nick);
            $vendor_nick = preg_replace('/\s+/u', '-', $vendor_nick);
            $vendor_nick = preg_replace('/[^a-z0-9\-]+/u', '', $vendor_nick);
            $vendor_nick = trim((string) $vendor_nick, '-');

            $wizard_session['store_name'] = $store_name;
            $wizard_session['store_description'] = $store_description;
            $wizard_session['vendor_nick'] = $vendor_nick;

            $slug = mb_strtolower($store_name);
            $slug = preg_replace('/\s+/u', '-', $slug);
            $slug = preg_replace('/[^a-z0-9\-]+/u', '', $slug);
            $slug = trim((string) $slug, '-');

            if (!$slug) {
                $slug = 'store-' . (int) $auth['user_id'];
            }

            $wizard_session['store_slug'] = $slug;

            return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.wizard?step=2'];
        }

        if ($step === 2) {
            $selected = isset($data['products']) ? (array) $data['products'] : [];
            $wizard_session['products'] = array_values(array_filter($selected, static function ($value) {
                return (string) $value !== '';
            }));

            return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.wizard?step=3'];
        }

        if ($step === 3) {
            $preset = isset($data['preset']) ? (string) $data['preset'] : 'light';
            if (!in_array($preset, ['light', 'dark', 'bright'], true)) {
                $preset = 'light';
            }
            $wizard_session['preset'] = $preset;

            return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.wizard?step=4'];
        }

        if ($step === 4) {
            $wizard_session['completed'] = true;

            /** @var \Tygh\Storefront\Repository $repository */
            $repository = Tygh::$app['storefront.repository'];
            $existing_storefront = $repository->findAvailableForCompanyId((int) $auth['company_id']);
            if ($existing_storefront) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $existing_url = $scheme . '://' . $existing_storefront->url . '/';
                return [CONTROLLER_STATUS_REDIRECT, $existing_url];
            }

            $vendor_nick = isset($wizard_session['vendor_nick']) ? (string) $wizard_session['vendor_nick'] : '';
            if ($vendor_nick === '') {
                $vendor_nick = 'vendor-' . (int) $auth['user_id'];
            }

            $base_domain = 'localhost';
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

            /** @var \Tygh\Storefront\Factory $factory */
            $factory = Tygh::$app['storefront.factory'];

            $default_storefront = $repository->findDefault();
            $default_theme = $default_storefront ? (string) $default_storefront->theme_name : '';
            $copy_layouts_from_storefront_id = $default_storefront ? (int) $default_storefront->storefront_id : null;

            $candidate_nick = $vendor_nick;
            $storefront_url = '';
            $existing = null;

            for ($i = 0; $i < 20; $i++) {
                $storefront_url = $candidate_nick . '.' . $base_domain;
                $existing = $repository->findByUrl($storefront_url);
                if (!$existing) {
                    break;
                }
                $candidate_nick = $vendor_nick . '-' . ($i + 2);
            }

            if ($existing) {
                fn_set_notification(NotificationSeverity::ERROR, __('error'), 'Не удалось подобрать свободный субдомен для витрины');
                return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.wizard?step=4'];
            }

            $storefront_data = [
                'url' => $storefront_url,
                'name' => $wizard_session['store_name'] ? (string) $wizard_session['store_name'] : ('Store ' . $candidate_nick),
                'status' => StorefrontStatuses::OPEN,
                'theme_name' => $default_theme,
                'company_ids' => [(int) $auth['company_id']],
                'extra' => [
                    'copy_layouts_from_storefront_id' => $copy_layouts_from_storefront_id,
                ],
            ];

            $storefront = $factory->fromArray($storefront_data);
            $result = $repository->save($storefront);
            $result->showNotifications(true);

            if (!$result->isSuccess()) {
                return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.wizard?step=4'];
            }

            $target_url = $scheme . '://' . $storefront_url . '/';

            return [CONTROLLER_STATUS_REDIRECT, $target_url];
        }
    }

    return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.wizard?step=' . $step];
}

Tygh::$app['view']->assign([
    'bw_step' => $step,
    'bw_data' => $wizard_session,
    'bw_storefront_url' => $bw_storefront_url,
]);

if ($mode === 'wizard') {
    if ($step === 1 && empty($wizard_session['started_notice_shown'])) {
        fn_set_notification('N', __('notice'), 'Запущен мастер настройки');
        $wizard_session['started_notice_shown'] = true;
    }
}

if ($mode === 'my_store') {
    Tygh::$app['view']->assign([
        'bw_data' => $wizard_session,
    ]);
}
