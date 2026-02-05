<?php

use Tygh\Enum\UserTypes;

defined('BOOTSTRAP') or die('Access denied');

/** @var string $mode */
/** @var array $auth */

if ($mode !== 'index') {
    return;
}

if (empty($auth['user_type']) || !UserTypes::isVendor($auth['user_type'])) {
    return;
}

$session = &Tygh::$app['session'];

/** @var \Tygh\Storefront\Repository $repository */
$repository = Tygh::$app['storefront.repository'];
$existing_storefront = $repository->findAvailableForCompanyId((int) $auth['company_id']);
if ($existing_storefront) {
    $session['brending_wizart_vendor_autostart_done'] = true;
    return;
}

if (empty($session['brending_wizart_vendor_autostart_done'])) {
    $session['brending_wizart_vendor_autostart_done'] = true;
    return [CONTROLLER_STATUS_REDIRECT, 'brending_wizart.wizard'];
}
