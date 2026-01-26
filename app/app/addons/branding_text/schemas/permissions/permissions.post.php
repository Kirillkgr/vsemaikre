<?php
/**
 * Permissions schema extension for Branding Text addon
 */
if (!defined('BOOTSTRAP')) { die('Access denied'); }

$schema['branding_text'] = array(
    'modes' => array(
        'constructor' => array(
            'permissions' => true, // доступен гостю на витрине
        ),
        'load' => array(
            'permissions' => true,
        ),
        'save' => array(
            'permissions' => true,
        ),
        'upload_logo' => array(
            'permissions' => true,
        ),
        'list_uploads' => array(
            'permissions' => true,
        ),
        'upload_preview' => array(
            'permissions' => true,
        ),
        'auto_apply' => array(
            'permissions' => true,
        ),
        'preview' => array(
            'permissions' => true,
        ),
        'ping' => array(
            'permissions' => true,
        ),
    ),
);

return $schema;
