<?php

use Tygh\Enum\YesNo;
use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        $mode === 'update'
        && Registry::ifGet('runtime.profile_updated', YesNo::NO) === YesNo::YES
        && empty($auth['user_id'])
        && !empty(Tygh::$app['session']['auth']['user_id'])
    ) {
        $_REQUEST['return_url'] = 'brending_wizart.wizard';
    }

    return;
}
