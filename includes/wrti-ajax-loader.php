<?php
defined('ABSPATH') || exit;

/**
 * Force-register AJAX endpoint for Trendyol attribute loader.
 * This guarantees WP recognizes the action no matter how plugin loads.
 */

add_action('wp_ajax_wr_trendyol_load_attributes', 'wrti_ajax_load_trendyol_attributes');

function wrti_ajax_load_trendyol_attributes() {

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wr_trendyol_product_nonce')) {
        wp_send_json_error(['message' => 'Geçersiz istek.']);
    }

    $category_id = isset($_POST['category_id']) ? absint($_POST['category_id']) : 0;
    $product_id  = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable

    if (!$category_id) {
        wp_send_json_error(['message' => 'Kategori ID boş geldi']);
    }

    if (!class_exists('\\WR\\Trendyol\\WR_Trendyol_Plugin')) {
        wp_send_json_error(['message' => 'API sınıfı yüklenemedi']);
    }

    $plugin = \WR\Trendyol\WR_Trendyol_Plugin::instance();
    $client = $plugin->get_api_client();
    $attributes = $client->get_category_attributes($category_id);

    if (is_wp_error($attributes) || empty($attributes)) {
        wp_send_json_error(['message' => 'Trendyol attribute API boş döndü']);
    }

    ob_start();

    echo '<div class="wr-ty-attributes">';

    foreach ($attributes as $attr) {
        $id   = isset($attr['attributeId']) ? esc_attr($attr['attributeId']) : 0;
        $name = isset($attr['name']) ? esc_html($attr['name']) : '';

        echo "<div class='wr-attr-block'>";
        echo "<label>{$name}</label>";

        $allow_multiple = !empty($attr['allowMultipleValues']);
        $allow_custom   = !empty($attr['allowCustom']);
        $values         = isset($attr['values']) ? $attr['values'] : (isset($attr['attributeValues']) ? $attr['attributeValues'] : []);

        if ($allow_custom || empty($values)) {
            echo "<input type='text' name='wr_attr[{$id}][custom]' class='wr-attr-input' />";
        }

        if (!empty($values)) {
            $multiple_attr = $allow_multiple ? ' multiple' : '';
            echo "<select name='wr_attr[{$id}][value_id][]'{$multiple_attr} class='wr-attr-select'>";
            echo "<option value=''>Seçin…</option>";

            foreach ($values as $value) {
                $val_id   = isset($value['id']) ? esc_attr($value['id']) : (isset($value['attributeValueId']) ? esc_attr($value['attributeValueId']) : '');
                $val_name = isset($value['name']) ? esc_html($value['name']) : (isset($value['attributeValue']) ? esc_html($value['attributeValue']) : '');

                if (!$val_id) {
                    continue;
                }

                echo "<option value='{$val_id}'>{$val_name}</option>";
            }

            echo '</select>';
        }

        echo "</div>";
    }

    echo '</div>';

    $html = ob_get_clean();

    wp_send_json_success(['html' => $html]);
}
