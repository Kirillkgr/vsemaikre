<?php
if (!defined('BOOTSTRAP')) { die('Access denied'); }

// Разрешаем фронтовый контроллер branding_text и его режимы
$schema['branding_text'] = [
    'modes' => [
        'constructor' => [
            'permissions' => true,
        ],
        'load' => [
            'permissions' => true,
        ],
        'save' => [
            'permissions' => true,
        ],
        'upload_logo' => [
            'permissions' => true,
        ],
        'list_uploads' => [
            'permissions' => true,
        ],
        'upload_preview' => [
            'permissions' => true,
        ],
        'auto_apply' => [
            'permissions' => true,
        ],
        'preview' => [
            'permissions' => true,
        ],
        'preview_for_product' => [
            'permissions' => true,
        ],
        'ping' => [
            'permissions' => true,
        ],
        'debug_ping' => [
            'permissions' => true,
        ],
    ],
];

return $schema;
