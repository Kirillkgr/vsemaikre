<?php

if (!defined('BOOTSTRAP')) { die('Access denied'); }

$schema['brending_wizart'] = [
    'modes' => [
        'wizard' => [
            'permissions' => true,
        ],
        'my_store' => [
            'permissions' => true,
        ],
        'save_my_store' => [
            'permissions' => true,
        ],
        'buy' => [
            'permissions' => true,
        ],
        'save' => [
            'permissions' => true,
        ],
        'buy_save' => [
            'permissions' => true,
        ],
    ],
];

return $schema;
