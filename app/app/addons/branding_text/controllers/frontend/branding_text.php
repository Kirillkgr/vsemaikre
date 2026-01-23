<?php
if (!defined('BOOTSTRAP')) { die('Access denied'); }

use Tygh\Registry;

// Простая проверка доступности контроллера: если нет режима — выведем OK и остановим
if ($mode === 'constructor') {
    $product_id = isset($_REQUEST['product_id']) ? (int) $_REQUEST['product_id'] : 0;
    Tygh::$app['view']->assign('product_id', $product_id);
    Tygh::$app['view']->display('addons/branding_text/views/branding_text/constructor.tpl');
    exit;
} elseif ($mode === 'ping') {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'branding_text controller OK';
    exit;
}
