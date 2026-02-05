<?php

defined('BOOTSTRAP') or die('Access denied');

/** @var array $schema */

$schema['controllers']['brending_wizart'] = [
    'modes' => [
        'my_store' => [
            'permissions' => true,
        ],
        'save_my_store' => [
            'permissions' => true,
        ],
        'wizard' => [
            'permissions' => true,
        ],
        'save' => [
            'permissions' => true,
        ],
    ],
];

return $schema;
