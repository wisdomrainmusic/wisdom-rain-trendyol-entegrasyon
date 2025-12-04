<?php
/**
 * Plugin Name: Wisdom Rain - Trendyol Entegrasyon
 * Description: Trendyol mağazanızı WooCommerce ile entegre edin. API ayarları, bağlantı testi ve temel entegrasyon altyapısını içerir.
 * Version: 0.1.0
 * Author: Wisdom Rain
 * Text Domain: wisdom-rain-trendyol-entegrasyon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WR_TRENDYOL_PLUGIN_VERSION' ) ) {
    define( 'WR_TRENDYOL_PLUGIN_VERSION', '0.1.0' );
}

if ( ! defined( 'WR_TRENDYOL_PLUGIN_FILE' ) ) {
    define( 'WR_TRENDYOL_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WR_TRENDYOL_PLUGIN_PATH' ) ) {
    define( 'WR_TRENDYOL_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WR_TRENDYOL_PLUGIN_DIR' ) ) {
    define( 'WR_TRENDYOL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'WR_TRENDYOL_PLUGIN_URL' ) ) {
    define( 'WR_TRENDYOL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

spl_autoload_register( function ( $class ) {
    if ( strpos( $class, 'WR\\Trendyol\\' ) !== 0 ) {
        return;
    }

    $class = str_replace( 'WR\\Trendyol\\', '', $class );
    $parts = explode( '\\', $class );

    $filename = 'class-' . strtolower( str_replace( '_', '-', array_pop( $parts ) ) ) . '.php';
    $path     = WR_TRENDYOL_PLUGIN_PATH . 'includes/';

    if ( ! empty( $parts ) && strtolower( $parts[0] ) === 'admin' ) {
        $path .= 'admin/';
    }

    $filepath = $path . $filename;

    if ( file_exists( $filepath ) ) {
        require_once $filepath;
    }
} );

if ( is_admin() ) {
    require_once WR_TRENDYOL_PLUGIN_PATH . 'includes/admin/class-wr-trendyol-categories.php';
    require_once WR_TRENDYOL_PLUGIN_PATH . 'wr-trendyol-admin.php';
}

// Custom Trendyol category manager module loader.
require_once __DIR__ . '/includes/wrti-ajax-loader.php';
require_once __DIR__ . '/wrti-plugin.php';

add_action( 'save_post_product', function( $post_id ) {
    if ( isset( $_POST['wr_trendyol_category'] ) ) {
        $category = sanitize_text_field( wp_unslash( $_POST['wr_trendyol_category'] ) );
    } elseif ( isset( $_POST['_wr_trendyol_category_id'] ) ) {
        $category = sanitize_text_field( wp_unslash( $_POST['_wr_trendyol_category_id'] ) );
    } else {
        return;
    }

    update_post_meta( $post_id, '_wr_trendyol_category_id', $category );
    update_post_meta( $post_id, '_trendyol_category_id', $category );
} );

function wr_trendyol_bootstrap() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Wisdom Rain - Trendyol Entegrasyon için WooCommerce eklentisi gereklidir.', 'wisdom-rain-trendyol-entegrasyon' ) . '</p></div>';
        } );

        return;
    }

    \WR\Trendyol\WR_Trendyol_Plugin::instance();
}
add_action( 'plugins_loaded', 'wr_trendyol_bootstrap' );

add_action( 'admin_init', function () {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    if ( get_option( 'wr_trendyol_categories_normalized' ) ) {
        return;
    }

    $data = get_option( 'wr_trendyol_categories' );

    if ( is_string( $data ) && $data !== '' ) {
        $decoded = json_decode( $data, true );
        if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
            update_option( 'wr_trendyol_categories', $decoded, false );
        }
    }

    update_option( 'wr_trendyol_categories_normalized', 1, false );
} );
