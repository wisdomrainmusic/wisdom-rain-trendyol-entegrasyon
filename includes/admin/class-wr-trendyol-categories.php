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
 * Trendyol Category Dropdown (stabil versiyon)
 *
 * Auto-repairs corrupted option data, safe renders, and supports ID persistence.
 */
function wr_trendyol_render_category_dropdown( $post ) {

    echo '<div class="wr-field">';
    echo '<label><strong>Trendyol Category</strong></label>';

    // 1) Kategorileri al
    $categories = get_option( 'wr_trendyol_categories' );

    // 2) BOZUKSA OTOMATİK TAMİR
    if ( ! is_array( $categories ) || empty( $categories ) ) {

        // LOG
        error_log( 'WR TRENDYOL: Categories corrupted, auto-refetch triggered.' );

        $api = null;

        if ( class_exists( '\\WR\\Trendyol\\WR_Trendyol_Plugin' ) ) {
            $plugin = \WR\Trendyol\WR_Trendyol_Plugin::instance();
            $api    = $plugin->get_api_client();
        }

        // Refetch
        if ( $api ) {
            $data = $api->get_categories();

            if ( $data && is_array( $data ) && ! is_wp_error( $data ) ) {
                update_option( 'wr_trendyol_categories', $data );
                $categories = $data;
            }
        }
    }

    // 3) Hala array değilse → kullanıcıya mesaj
    if ( ! is_array( $categories ) || empty( $categories ) ) {
        echo '<span style="color:#d00;">Kategori listesi yüklenemedi.</span>';
        echo '</div>';
        return;
    }

    // 4) Kaydedilmiş kategori ID
    $saved = get_post_meta( $post->ID, '_wr_trendyol_category_id', true );

    echo '<select id="wr_trendyol_category_id" name="wr_trendyol_category_id" style="width:100%;">';
    echo '<option value="">Kategori Seçin</option>';

    // 5) Hiyerarşik dropdown oluştur
    wr_trendyol_print_recursive_options( $categories, $saved );

    echo '</select>';
    echo '</div>';
}

/**
 * Recursive dropdown builder
 */
function wr_trendyol_print_recursive_options( $items, $saved, $prefix = '' ) {
    foreach ( $items as $item ) {

        if ( ! isset( $item['id'], $item['name'] ) ) {
            continue;
        }

        $selected = ( (string) $saved === (string) $item['id'] ) ? 'selected' : '';

        echo '<option value="' . esc_attr( $item['id'] ) . '" ' . $selected . '>' .
             esc_html( $prefix . $item['name'] ) .
             '</option>';

        // Alt kategori varsa
        if ( ! empty( $item['subCategories'] ) ) {
            wr_trendyol_print_recursive_options( $item['subCategories'], $saved, $prefix . '— ' );
        }
    }
}

add_action(
    'save_post',
    function ( $post_id ) {

        $posted_category = null;

        if ( isset( $_POST['wr_trendyol_category_id'] ) ) {
            $posted_category = sanitize_text_field( wp_unslash( $_POST['wr_trendyol_category_id'] ) );
        } elseif ( isset( $_POST['wr_trendyol_category'] ) ) { // Legacy alan.
            $posted_category = sanitize_text_field( wp_unslash( $_POST['wr_trendyol_category'] ) );
        }

        if ( null === $posted_category ) {
            return;
        }

        update_post_meta( $post_id, '_trendyol_category_id', $posted_category );
        update_post_meta( $post_id, '_wr_trendyol_category_id', $posted_category );
    }
);
