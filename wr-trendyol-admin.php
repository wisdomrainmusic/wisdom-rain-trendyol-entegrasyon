<?php
// ===============================
// 1) LOAD ADMIN SCRIPTS (GUARANTEED)
// ===============================
add_action('admin_enqueue_scripts', function () {
    // Legacy admin scripts intentionally disabled; canonical loader enqueued in WR_Trendyol_Product_Tab for product screens.
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if ($screen && $screen->post_type === 'product') {
        return;
    }
});

// ===============================
// 2) AJAX HANDLER — CANONICAL
// ===============================
// Attribute loader AJAX is registered inside WR_Trendyol_Product_Tab::ajax_load_attributes()
// with nonce key `wr_trendyol_product_nonce`. No additional handlers should be
// registered here to avoid duplicate hooks or mismatched nonces.
