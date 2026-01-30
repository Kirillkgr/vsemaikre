<?php
if (!defined('BOOTSTRAP')) { die('Access denied'); }

if (defined('AREA') && AREA === 'C') {
    if (empty($_COOKIE['bt_guest_id'])) {
        $auth = isset(\Tygh\Tygh::$app['session']['auth']) && is_array(\Tygh\Tygh::$app['session']['auth']) ? \Tygh\Tygh::$app['session']['auth'] : [];
        $user_id = !empty($auth['user_id']) ? (int) $auth['user_id'] : 0;

        if ($user_id === 0) {
            $guest_id = '';
            if (isset(\Tygh\Tygh::$app['session']['bt_guest_id'])) {
                $guest_id = (string) \Tygh\Tygh::$app['session']['bt_guest_id'];
            }
            if (!$guest_id) {
                try {
                    $guest_id = 'btg_' . bin2hex(random_bytes(16));
                } catch (\Exception $e) {
                    $guest_id = 'btg_' . md5(uniqid('', true));
                }
            }

            @setcookie('bt_guest_id', $guest_id, time() + 60 * 60 * 24 * 30, '/');
            \Tygh\Tygh::$app['session']['bt_guest_id'] = $guest_id;
        }
    }

    fn_register_hooks(
        'get_products_post',
        'get_product_data_post',
        'get_cart_product_data',
        'image_to_display_post'
    );
}
