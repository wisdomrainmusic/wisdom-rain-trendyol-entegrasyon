<?php
namespace WR\Trendyol;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WR_Trendyol_Plugin {
    const OPTION_KEY = 'wr_trendyol_settings';
    const ENV_PROD   = 'prod';
    const ENV_SANDBOX = 'sandbox';

    /**
     * Singleton instance.
     *
     * @var WR_Trendyol_Plugin
     */
    protected static $instance;

    /**
     * Default user agent string.
     *
     * @var string
     */
    protected $default_user_agent = '';

    /**
     * Cached settings.
     *
     * @var array
     */
    protected $settings = [];

    /**
     * Initialize plugin.
     *
     * @return WR_Trendyol_Plugin
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Hook into WordPress.
     */
    protected function __construct() {
        $this->default_user_agent = 'WisdomRain-Trendyol-WooCommerce/' . WR_TRENDYOL_PLUGIN_VERSION;

        add_action( 'init', [ $this, 'load_textdomain' ] );
        add_action( 'init', [ $this, 'maybe_setup' ] );
    }

    /**
     * Load plugin translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'wisdom-rain-trendyol-entegrasyon', false, dirname( plugin_basename( WR_TRENDYOL_PLUGIN_FILE ) ) . '/languages' );
    }

    /**
     * Initialize settings page and hooks.
     */
    public function maybe_setup() {
        $this->get_settings();

        if ( is_admin() ) {
            new Admin\WR_Trendyol_Settings_Page( $this );
            new Admin\WR_Trendyol_Product_Tab( $this );
        }
    }

    /**
     * Return plugin settings with defaults.
     *
     * @return array
     */
    public function get_settings() {
        if ( ! empty( $this->settings ) ) {
            return $this->settings;
        }

        $defaults = [
            'seller_id'   => '',
            'api_key'     => '',
            'api_secret'  => '',
            'environment' => self::ENV_PROD,
            'user_agent'  => '',
            'debug'       => 0,
            'cargo_company_id'   => 0,
            'delivery_duration'  => 1,
            'shipment_address_id'=> 0,
            'return_address_id'  => 0,
        ];

        $settings = get_option( self::OPTION_KEY, [] );

        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        // Backwards compatibility with earlier option keys/values.
        if ( empty( $settings['seller_id'] ) && ! empty( $settings['supplier_id'] ) ) {
            $settings['seller_id'] = $settings['supplier_id'];
        }

        if ( isset( $settings['environment'] ) && 'production' === $settings['environment'] ) {
            $settings['environment'] = self::ENV_PROD;
        }

        $this->settings = wp_parse_args( $settings, $defaults );

        if ( empty( $this->settings['user_agent'] ) ) {
            $this->settings['user_agent'] = $this->get_default_user_agent( $this->settings['seller_id'] );
        }

        return $this->settings;
    }

    /**
     * Create API client with current settings.
     *
     * @return WR_Trendyol_API_Client
     */
    public function get_api_client() {
        return new WR_Trendyol_API_Client( $this->get_settings() );
    }

    /**
     * Default User-Agent header value.
     *
     * @return string
     */
    public function get_default_user_agent( $seller_id = '' ) {
        if ( ! empty( $seller_id ) ) {
            return sprintf( '%s - Self Integration', $seller_id );
        }

        return $this->default_user_agent;
    }
}
