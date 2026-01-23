<?php
if (!defined('BOOTSTRAP')) { die('Access denied'); }

// Разрешаем фронтовый контроллер branding_text и его режимы
$schema['branding_text'] = [
    'modes' => [
        'constructor' => [
            'permissions' => true,
        ],
        'ping' => [
            'permissions' => true,
        ],
    ],
];

return $schema;
