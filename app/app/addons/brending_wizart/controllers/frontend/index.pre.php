<?php

if (!defined('BOOTSTRAP')) {
    die('Access denied');
}

$storefront = Tygh::$app['storefront'];
if (!$storefront) {
    return;
}

$company_ids = method_exists($storefront, 'getCompanyIds') ? (array) $storefront->getCompanyIds() : [];
$company_ids = array_values(array_filter(array_map('intval', $company_ids)));

if (count($company_ids) === 1 && empty($storefront->is_default)) {
    $company_id = (int) reset($company_ids);
    return [CONTROLLER_STATUS_REDIRECT, 'companies.products?company_id=' . $company_id];
}
