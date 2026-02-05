<?php

use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

$storefront = Tygh::$app['storefront'];
$background_color = '';
$accent_color = '';
$text_color = '';

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

if ($storefront && !empty($storefront->storefront_id)) {
    $settings = Tygh::$app['db']->getRow(
        'SELECT background_color, accent_color, text_color FROM ?:brending_wizart_storefront_settings WHERE storefront_id = ?i',
        (int) $storefront->storefront_id
    );
    if (is_array($settings) && $settings) {
        $background_color = isset($settings['background_color']) ? (string) $settings['background_color'] : '';
        $accent_color = isset($settings['accent_color']) ? (string) $settings['accent_color'] : '';
        $text_color = isset($settings['text_color']) ? (string) $settings['text_color'] : '';
    }
}

if ($background_color !== '') {
    Tygh::$app['view']->assign('bw_background_color', $background_color);
}
if ($accent_color !== '') {
    Tygh::$app['view']->assign('bw_accent_color', $accent_color);
}
if ($text_color !== '') {
    Tygh::$app['view']->assign('bw_text_color', $text_color);
}
