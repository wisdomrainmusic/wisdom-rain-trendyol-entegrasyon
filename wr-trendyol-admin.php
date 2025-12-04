<?php
// ===============================
// 1) LOAD ADMIN SCRIPTS (GUARANTEED)
// ===============================
add_action('admin_enqueue_scripts', function () {
    wp_enqueue_script(
        'wr-trendyol-admin',
        plugin_dir_url(__FILE__) . 'assets/js/wr-trendyol-admin.js',
        ['jquery'],
        '1.0',
        true
    );

    wp_enqueue_script(
        'wr-trendyol-attribute-loader',
        plugin_dir_url(__FILE__) . 'assets/js/attribute_yukle.js',
        ['jquery'],
        '1.0',
        true
    );

    wp_localize_script('wr-trendyol-admin', 'wrTrendyol', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('wr_trendyol_nonce')
    ]);
});

// ===============================
// 2) AJAX HANDLER — FORCE ENABLED
// ===============================
// add_action('wp_ajax_wr_load_attributes', 'wr_load_attributes_callback');

/*
function wr_load_attributes_callback() {

    check_ajax_referer('wr_trendyol_nonce', 'nonce');

    // Güvenlik: categoryId boşsa bitir
    if (!isset($_POST['categoryId']) || empty($_POST['categoryId'])) {
        wp_send_json_error(['message' => 'No category ID']);
    }

    $category_id = intval($_POST['categoryId']);
    $seller_id   = get_option('wr_trendyol_seller_id');

    if (!$seller_id) {
        wp_send_json_error(['message' => 'Seller ID missing']);
    }

    $url = "https://apigw.trendyol.com/integration/ecgw/v1/$seller_id/lookup/product-categories/$category_id/attributes";

    $username = get_option('wr_trendyol_api_key');
    $password = get_option('wr_trendyol_api_secret');

    $args = [
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
            'Content-Type'  => 'application/json'
        ],
        'timeout' => 30
    ];

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => $response->get_error_message()]);
    }

    $body = wp_remote_retrieve_body($response);
    $json = json_decode($body, true);

    // Log at — debug için
    error_log("WR ATTR RESPONSE: " . print_r($json, true));

    wp_send_json_success($json);
}
*/
