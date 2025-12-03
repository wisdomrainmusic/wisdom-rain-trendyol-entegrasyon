<?php
defined('ABSPATH') || exit;

/**
 * Force-register AJAX endpoint for Trendyol attribute loader.
 * This guarantees WP recognizes the action no matter how plugin loads.
 */

add_action('wp_ajax_wr_trendyol_load_attributes', 'wrti_ajax_load_trendyol_attributes');

function wrti_ajax_load_trendyol_attributes() {

    // Security
    if (!isset($_POST['nonce']) ||
        !wp_verify_nonce($_POST['nonce'], 'wr_trendyol_nonce')) {
        wp_send_json_error(['msg' => 'Nonce failed']);
    }

    $category_id = intval($_POST['category_id']);
    $product_id  = intval($_POST['product_id']);

    if (!$category_id) {
        wp_send_json_error(['msg' => 'Invalid category id']);
    }

    // Load API
    if (!class_exists('WR_Trendyol_API')) {
        wp_send_json_error(['msg' => 'API class missing']);
    }

    $attributes = WR_Trendyol_API::get_category_attributes($category_id);

    if (empty($attributes)) {
        wp_send_json_error(['msg' => 'No attributes returned']);
    }

    // Build response HTML
    ob_start();

    echo '<div class="wr-ty-attributes">';

    foreach ($attributes as $attr) {

        $id   = esc_attr($attr['attributeId']);
        $name = esc_html($attr['name']);

        echo "<div class='wr-attr-block'>";
        echo "<label>{$name}</label>";

        $multiple = $attr['allowMultipleValues'] ? 'multiple' : '';

        echo "<select name='wr_attr[{$id}][]' {$multiple} class='wr-attr-select'>";

        foreach ($attr['values'] as $value) {
            $val_id   = esc_attr($value['id']);
            $val_name = esc_html($value['name']);
            echo "<option value='{$val_id}'>{$val_name}</option>";
        }

        echo "</select>";
        echo "</div>";
    }

    echo '</div>';

    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}
