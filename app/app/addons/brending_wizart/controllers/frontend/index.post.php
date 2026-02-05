<?php

use Tygh\Registry;

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

$storefront = Tygh::$app['storefront'];
$extra = is_array($storefront->extra) ? $storefront->extra : [];
$bw = isset($extra['brending_wizart']) && is_array($extra['brending_wizart']) ? $extra['brending_wizart'] : [];

$background_color = isset($bw['background_color']) ? (string) $bw['background_color'] : '';
$accent_color = isset($bw['accent_color']) ? (string) $bw['accent_color'] : '';
$text_color = isset($bw['text_color']) ? (string) $bw['text_color'] : '';

if ($background_color !== '') {
    Tygh::$app['view']->assign('bw_background_color', $background_color);
}
if ($accent_color !== '') {
    Tygh::$app['view']->assign('bw_accent_color', $accent_color);
}
if ($text_color !== '') {
    Tygh::$app['view']->assign('bw_text_color', $text_color);
}
