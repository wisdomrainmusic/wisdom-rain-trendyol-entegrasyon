<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WR_Trendyol_Categories {

    const OPTION_KEY = 'wr_trendyol_categories';

    /**
     * Kategori ağacını güvenli şekilde getirir.
     * - Option string ise JSON decode eder
     * - Boş/bozuk ise API'den veya fallback JSON'dan yeniden çeker
     * - Son halini OPTION'a array olarak yazar (autoload = no)
     */
    public static function get_normalized_tree() {
        $data = get_option( self::OPTION_KEY );

        // 1) JSON string ise decode et
        if ( is_string( $data ) && $data !== '' ) {
            $decoded = json_decode( $data, true );
            if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
                $data = $decoded;
            } else {
                $data = array();
            }
        }

        // 2) Hala array değilse veya boşsa -> API / fallback dosyadan çek
        if ( ! is_array( $data ) || empty( $data ) ) {
            if ( method_exists( __CLASS__, 'get_categories_raw' ) ) {
                $data = self::get_categories_raw(); // Raporda zaten var
            } else {
                $data = array();
            }

            if ( is_array( $data ) && ! empty( $data ) ) {
                // Kategori ağacını autoload = no olacak şekilde kaydet
                update_option( self::OPTION_KEY, $data, false );
            }
        }

        return is_array( $data ) ? $data : array();
    }

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
        $tree = self::get_normalized_tree();

        // Rapora göre bazen ['categories'], bazen ['items'], bazen direkt array
        if ( isset( $tree['categories'] ) && is_array( $tree['categories'] ) ) {
            $nodes = $tree['categories'];
        } elseif ( isset( $tree['items'] ) && is_array( $tree['items'] ) ) {
            $nodes = $tree['items'];
        } else {
            $nodes = $tree;
        }

        $options = array();
        self::walk_categories( $nodes, $options );

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

    /**
     * Recursive kategori yürüyücüsü.
     */
    protected static function walk_categories( $nodes, &$options, $prefix = '' ) {
        if ( ! is_array( $nodes ) ) {
            return;
        }

        foreach ( $nodes as $cat ) {
            $id   = isset( $cat['id'] ) ? $cat['id'] : null;
            $name = '';

            if ( isset( $cat['fullPath'] ) && '' !== $cat['fullPath'] ) {
                // Bazı API cevapları fullPath taşıyor
                $name = $cat['fullPath'];
            } elseif ( isset( $cat['name'] ) ) {
                $name = $cat['name'];
            }

            if ( ! $id || '' === $name ) {
                continue;
            }

            $label          = $prefix ? $prefix . ' > ' . $name : $name;
            $options[ $id ] = $label;

            if ( ! empty( $cat['subCategories'] ) && is_array( $cat['subCategories'] ) ) {
                self::walk_categories( $cat['subCategories'], $options, $label );
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
 * Helper tabanlı, normalize edilmiş kategori listesini güvenle render eder.
 */
function wr_trendyol_render_category_dropdown( $post ) {
    if ( ! class_exists( 'WR_Trendyol_Categories' ) ) {
        echo '<span style="color:red;">Trendyol kategori yöneticisi bulunamadı.</span>';
        return;
    }

    $options = WR_Trendyol_Categories::get_flat_options();

    if ( empty( $options ) ) {
        echo '<span style="color:red;">Trendyol kategori listesi yüklenemedi.</span>';
        return;
    }

    $selected = get_post_meta( $post->ID, '_wr_trendyol_category_id', true );

    echo '<select id="wr_trendyol_category_id" name="wr_trendyol_category_id" style="min-width:280px;">';

    echo '<option value="">' . esc_html__( 'Bir Trendyol kategorisi seçin', 'wr-trendyol' ) . '</option>';

    foreach ( $options as $id => $label ) {
        printf(
            '<option value="%d"%s>%s</option>',
            (int) $id,
            selected( (int) $selected, (int) $id, false ),
            esc_html( $label )
        );
    }

    echo '</select>';
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
