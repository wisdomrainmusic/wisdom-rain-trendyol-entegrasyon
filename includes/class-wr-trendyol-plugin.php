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
     * Trendyol tarafında geçerli kargo firmaları (ID, slug ve label).
     *
     * @return array<string,array{ id:int, label:string }>
     */
    public static function get_cargo_company_map() {
        return [
            'yurtici'   => [ 'id' => 1, 'label' => 'Yurtiçi Kargo' ],
            'aras'      => [ 'id' => 2, 'label' => 'Aras Kargo' ],
            'mng'       => [ 'id' => 3, 'label' => 'MNG Kargo' ],
            'surat'     => [ 'id' => 4, 'label' => 'Sürat Kargo' ],
            'ptt'       => [ 'id' => 5, 'label' => 'PTT Kargo' ],
            'ups'       => [ 'id' => 6, 'label' => 'UPS' ],
            'hepsijet'  => [ 'id' => 7, 'label' => 'Hepsijet' ],
            'tyexpress' => [ 'id' => 8, 'label' => 'Trendyol Express' ],
            'dhl'       => [ 'id' => 9, 'label' => 'DHL' ],
        ];
    }

    /**
     * cargoCompanyId -> label eşlemesi.
     *
     * @return array<int,string>
     */
    public static function get_cargo_company_labels() {
        $labels = [];

        foreach ( self::get_cargo_company_map() as $data ) {
            $labels[ (int) $data['id'] ] = $data['label'];
        }

        return $labels;
    }

    /**
     * Trendyol whitelist'i (sadece sayısal ID listesi).
     *
     * @return int[]
     */
    public static function get_allowed_cargo_company_ids() {
        return array_values( array_map( static function( $data ) {
            return (int) $data['id'];
        }, self::get_cargo_company_map() ) );
    }

    /**
     * UI veya eski meta değerini Trendyol cargoCompanyId'ye çevirir.
     *
     * @param mixed $value Value from UI/meta/settings.
     *
     * @return int|null Null -> geçersiz / tanınmadı.
     */
    public static function normalize_cargo_company_value( $value ) {
        $map = self::get_cargo_company_map();

        if ( is_string( $value ) && isset( $map[ $value ] ) ) {
            return (int) $map[ $value ]['id'];
        }

        $int_value = absint( $value );
        if ( $int_value > 0 ) {
            foreach ( $map as $slug => $data ) {
                if ( (int) $data['id'] === $int_value ) {
                    return (int) $data['id'];
                }
            }
        }

        if ( '' !== $value && null !== $value ) {
            error_log( sprintf( 'WR TRENDYOL WARN: Invalid cargoCompanyId value detected: %s', wp_json_encode( $value ) ) );
        }

        return null;
    }

    /**
     * cargoCompanyId whitelist kontrolü.
     *
     * @param int $cargo_company_id
     *
     * @return bool
     */
    public static function is_allowed_cargo_company_id( $cargo_company_id ) {
        return in_array( (int) $cargo_company_id, self::get_allowed_cargo_company_ids(), true );
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
