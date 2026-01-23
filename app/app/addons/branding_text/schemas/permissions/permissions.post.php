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
        'ping' => array(
            'permissions' => true,
        ),
    ),
);

return $schema;
