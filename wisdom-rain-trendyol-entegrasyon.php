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
}

// Custom Trendyol category manager module loader.
require_once __DIR__ . '/wrti-plugin.php';

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
