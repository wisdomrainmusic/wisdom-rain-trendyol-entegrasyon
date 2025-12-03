<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WR_Trendyol_Categories {

    /**
     * Read Trendyol categories JSON and return associative array.
     *
     * @return array
     */
    public static function get_categories_raw() {
        // API client instance al
        if ( class_exists( '\\WR\\Trendyol\\WR_Trendyol_Plugin' ) ) {

            $plugin = \WR\Trendyol\WR_Trendyol_Plugin::instance();
            $client = $plugin->get_api_client();

            // API’den kategori çek
            $cats = $client->get_categories();

            if ( is_array( $cats ) && ! empty( $cats ) ) {
                return array( 'categories' => $cats );
            }
        }

        // Fallback: eski JSON dosyasına bak (isteğe bağlı)
        $file = WR_TRENDYOL_PLUGIN_DIR . 'assets/data/trendyol-categories.json';
        if ( file_exists( $file ) ) {
            $json = file_get_contents( $file );
            $data = json_decode( $json, true );
            if ( is_array( $data ) ) {
                return $data;
            }
        }

        return array();
    }

    /**
     * Return flat id => "Parent > Child" list for single dropdown usage.
     *
     * @return array
     */
    public static function get_flat_options() {
        $raw = self::get_categories_raw();

        $options = array();

        if ( isset( $raw['categories'] ) && is_array( $raw['categories'] ) ) {
            $items = $raw['categories'];
        } elseif ( isset( $raw['items'] ) && is_array( $raw['items'] ) ) {
            // Eski format desteği
            $items = $raw['items'];
        } elseif ( is_array( $raw ) ) {
            // Fallback: API direkt array dönerse
            $items = $raw;
        } else {
            return array();
        }

        foreach ( $items as $item ) {
            self::walk_category( $item, array(), $options );
        }

        asort( $options, SORT_NATURAL | SORT_FLAG_CASE );

        return $options;
    }

    /**
     * Recursive walker to build path labels.
     *
     * @param array $node
     * @param array $parents
     * @param array $options
     */
    protected static function walk_category( $node, $parents, &$options ) {
        if ( empty( $node['id'] ) || empty( $node['name'] ) ) {
            return;
        }

        $current_path = array_merge( $parents, array( $node['name'] ) );
        $label        = implode( ' > ', $current_path );
        $id           = (string) $node['id'];

        $options[ $id ] = $label;

        if ( ! empty( $node['subCategories'] ) && is_array( $node['subCategories'] ) ) {
            foreach ( $node['subCategories'] as $child ) {
                self::walk_category( $child, $current_path, $options );
            }
        }
    }
}

if ( ! function_exists( 'wr_trendyol_get_category_options' ) ) {
    /**
     * Helper: get flat Trendyol category options.
     *
     * @return array
     */
    function wr_trendyol_get_category_options() {
        return WR_Trendyol_Categories::get_flat_options();
    }
}

/**
 * Trendyol Category Dropdown (FULL FIXED VERSION)
 * Outputs: <option value="categoryId">Parent > Child > SubChild</option>
 */
function wr_trendyol_render_category_dropdown( $post ) {

    // Ürüne daha önce seçilmiş kategori var mı?
    $selected_id = get_post_meta( $post->ID, '_trendyol_category_id', true );

    if ( ! $selected_id ) {
        $selected_id = get_post_meta( $post->ID, '_wr_trendyol_category_id', true );
    }

    // Kategori ağacını alıyoruz (JSON → array)
    $categories = get_option( 'wr_trendyol_category_tree' );

    if ( ( ! $categories || ! is_array( $categories ) ) && function_exists( 'wr_trendyol_get_category_options' ) ) {
        $legacy_tree = get_option( 'wrti_category_tree' );
        if ( $legacy_tree ) {
            $decoded = json_decode( $legacy_tree, true );
            if ( is_array( $decoded ) ) {
                $categories = isset( $decoded['categories'] ) ? $decoded['categories'] : $decoded;
            }
        }
    }

    if ( ! $categories || ! is_array( $categories ) ) {
        echo '<p style="color:red;">Kategori listesi yüklenemedi.</p>';
        return;
    }

    echo '<select id="wr_trendyol_category" name="wr_trendyol_category" style="width:100%;">';
    echo '<option value="">— Select Trendyol Category —</option>';

    // Recursive builder
    wr_trendyol_render_options_recursive( $categories, '', $selected_id );

    echo '</select>';
}

/**
 * Recursive option builder
 */
function wr_trendyol_render_options_recursive( $items, $prefix, $selected_id ) {

    foreach ( $items as $item ) {

        $id   = isset( $item['id'] ) ? $item['id'] : '';
        $name = isset( $item['name'] ) ? $item['name'] : '';

        if ( ! $id ) {
            continue;
        }

        // Option label (path)
        $label = $prefix ? $prefix . ' > ' . $name : $name;

        // Selected?
        $sel = ( (string) $selected_id === (string) $id ) ? 'selected' : '';

        echo '<option value="' . esc_attr( $id ) . '" ' . $sel . '>' . esc_html( $label ) . '</option>';

        // If children exist → recursion
        if ( isset( $item['subCategories'] ) && is_array( $item['subCategories'] ) && count( $item['subCategories'] ) > 0 ) {
            wr_trendyol_render_options_recursive( $item['subCategories'], $label, $selected_id );
        }
    }
}

add_action(
    'save_post',
    function ( $post_id ) {

        if ( ! isset( $_POST['wr_trendyol_category'] ) ) {
            return;
        }

        $cat_id = sanitize_text_field( wp_unslash( $_POST['wr_trendyol_category'] ) );

        update_post_meta( $post_id, '_trendyol_category_id', $cat_id );
        update_post_meta( $post_id, '_wr_trendyol_category_id', $cat_id );
    }
);
