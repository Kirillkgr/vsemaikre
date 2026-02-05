<?php

use Tygh\Enum\ObjectStatuses;

defined('BOOTSTRAP') or die('Access denied');

/** @var array $schema */

$schema['central']['seller_tools']['items']['brending_wizart'] = [
    'title' => 'Мастер настройки витрины',
    'href' => 'brending_wizart.my_store',
    'position' => 50,
    'status' => ObjectStatuses::ACTIVE,
];

return $schema;
