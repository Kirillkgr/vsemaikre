<?php
if (!defined('BOOTSTRAP')) { die('Access denied'); }

// Make product listing blocks cache depend on the current user
// (personal previews) while keeping TTL intact.
if (isset($schema['products']['cache'])) {
    if (!isset($schema['products']['cache']['auth_handlers']) || !is_array($schema['products']['cache']['auth_handlers'])) {
        $schema['products']['cache']['auth_handlers'] = [];
    }
    if (!in_array('user_id', $schema['products']['cache']['auth_handlers'], true)) {
        $schema['products']['cache']['auth_handlers'][] = 'user_id';
    }

    if (!isset($schema['products']['cache']['session_handlers']) || !is_array($schema['products']['cache']['session_handlers'])) {
        $schema['products']['cache']['session_handlers'] = [];
    }
    if (!in_array('bt_cache_bust', $schema['products']['cache']['session_handlers'], true)) {
        $schema['products']['cache']['session_handlers'][] = 'bt_cache_bust';
    }

    if (!in_array('bt_guest_id', $schema['products']['cache']['session_handlers'], true)) {
        $schema['products']['cache']['session_handlers'][] = 'bt_guest_id';
    }
}

return $schema;
