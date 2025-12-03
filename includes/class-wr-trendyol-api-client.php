<?php
namespace WR\Trendyol;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WR_Trendyol_API_Client {
    /**
     * Seller/Supplier ID.
     *
     * @var string
     */
    protected $seller_id = '';

    /**
     * Trendyol API key (username).
     *
     * @var string
     */
    protected $api_key = '';

    /**
     * Trendyol API secret (password).
     *
     * @var string
     */
    protected $api_secret = '';

    /**
     * Selected environment.
     *
     * @var string
     */
    protected $environment = WR_Trendyol_Plugin::ENV_PROD;

    /**
     * User agent header.
     *
     * @var string
     */
    protected $user_agent = '';

    /**
     * Debug flag.
     *
     * @var bool
     */
    protected $debug = false;

    /**
     * Base URLs for environments.
     *
     * @var array
     */
    protected $environments = [
        // Yeni APIGW hostları (prod & stage)
        WR_Trendyol_Plugin::ENV_PROD    => 'https://apigw.trendyol.com',
        WR_Trendyol_Plugin::ENV_SANDBOX => 'https://stageapigw.trendyol.com',
    ];

    /**
     * Constructor.
     *
     * @param array $settings Plugin settings.
     */
    public function __construct( array $settings ) {
        $this->seller_id   = isset( $settings['seller_id'] ) ? trim( (string) $settings['seller_id'] ) : '';
        $this->api_key     = isset( $settings['api_key'] ) ? trim( (string) $settings['api_key'] ) : '';
        $this->api_secret  = isset( $settings['api_secret'] ) ? trim( (string) $settings['api_secret'] ) : '';
        $this->environment = isset( $settings['environment'] ) && WR_Trendyol_Plugin::ENV_SANDBOX === $settings['environment'] ? WR_Trendyol_Plugin::ENV_SANDBOX : WR_Trendyol_Plugin::ENV_PROD;
        $this->debug       = ! empty( $settings['debug'] );

        $user_agent       = isset( $settings['user_agent'] ) ? trim( (string) $settings['user_agent'] ) : '';
        $this->user_agent = '' !== $user_agent ? $user_agent : $this->get_default_user_agent();
    }

    /**
     * Get base URL for environment.
     *
     * @return string
     */
    public function get_base_url() {
        if ( ! isset( $this->environments[ $this->environment ] ) ) {
            $this->environment = WR_Trendyol_Plugin::ENV_PROD;
        }

        return trailingslashit( $this->environments[ $this->environment ] );
    }

    /**
     * Default User-Agent header.
     *
     * @return string
     */
    protected function get_default_user_agent() {
        if ( ! empty( $this->seller_id ) ) {
            return sprintf( '%s - Self Integration', $this->seller_id );
        }

        return 'WisdomRain-Trendyol-WooCommerce/' . WR_TRENDYOL_PLUGIN_VERSION;
    }

    /**
     * Build authorization header.
     *
     * @return string
     */
    protected function build_auth_header() {
        return 'Basic ' . base64_encode( $this->api_key . ':' . $this->api_secret );
    }

    /**
     * Base request headers.
     *
     * @return array
     */
    protected function get_base_headers() {
        return [
            'Authorization' => $this->build_auth_header(),
            'User-Agent'    => $this->user_agent,
            'Accept'        => 'application/json',
        ];
    }

    /**
     * Perform a request to Trendyol API.
     *
     * @param string $method HTTP method.
     * @param string $path   Endpoint path relative to base URL.
     * @param array  $args   Optional request args.
     *
     * @return array|WP_Error
     */
    public function request( $method, $path, $args = [] ) {
        if ( empty( $this->api_key ) || empty( $this->api_secret ) ) {
            return new WP_Error( 'wr_trendyol_missing_credentials', __( 'API key ve secret değerleri gerekli.', 'wisdom-rain-trendyol-entegrasyon' ) );
        }

        $is_absolute_url = (bool) parse_url( $path, PHP_URL_SCHEME );

        $url = $is_absolute_url ? $path : $this->get_base_url() . ltrim( $path, '/' );

        if ( ! empty( $args['query'] ) && is_array( $args['query'] ) ) {
            $url = add_query_arg( $args['query'], $url );
        }

        $headers = $this->get_base_headers();

        if ( ! empty( $args['headers'] ) && is_array( $args['headers'] ) ) {
            $headers = array_merge( $headers, $args['headers'] );
        }

        $request_args = wp_parse_args(
            $args,
            [
                'method'    => strtoupper( $method ),
                'headers'   => $headers,
                'timeout'   => 20,
                'sslverify' => true,
            ]
        );

        if ( ! empty( $args['body'] ) && is_array( $args['body'] ) ) {
            $request_args['body']              = wp_json_encode( $args['body'] );
            $request_args['headers']['Content-Type'] = 'application/json';
        }

        $response = wp_remote_request( $url, $request_args );

        if ( is_wp_error( $response ) ) {
            return $this->wrap_error( 'http_request_failed', $response->get_error_message(), [
                'url'  => $url,
                'args' => $this->debug ? $request_args : null,
            ] );
        }

        $status = wp_remote_retrieve_response_code( $response );
        $body   = wp_remote_retrieve_body( $response );

        $decoded = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $decoded = $body;
        }

        if ( $status >= 400 ) {
            $message = is_array( $decoded ) && isset( $decoded['message'] ) ? $decoded['message'] : __( 'Trendyol API error', 'wisdom-rain-trendyol-entegrasyon' );

            return $this->wrap_error( 'trendyol_api_error', $message, [
                'status' => $status,
                'body'   => $this->debug ? $decoded : null,
                'url'    => $url,
            ] );
        }

        return [
            'status' => $status,
            'body'   => $decoded,
            'raw'    => $this->debug ? $response : null,
        ];
    }

    /**
     * Health check endpoint for Trendyol connection.
     *
     * @return array|WP_Error
     */
    public function health_check() {
        if ( empty( $this->seller_id ) ) {
            return new WP_Error( 'wr_trendyol_missing_seller_id', __( 'Supplier/Seller ID is required for the health check.', 'wisdom-rain-trendyol-entegrasyon' ) );
        }

        $path = sprintf( '/integration/sellers/%s/addresses', rawurlencode( $this->seller_id ) );

        return $this->request( 'GET', $path );
    }

    /**
     * Trendyol kategori listesini çeker (transient cache ile)
     *
     * @return array|WP_Error
     */
    public function get_categories() {

        $cached = get_transient( 'wr_trendyol_categories' );
        if ( $cached !== false ) {
            return $cached;
        }

        error_log( 'WR TRENDYOL DEBUG: CATEGORY REQUEST START' );

        // NEW OFFICIAL ENDPOINT (apigw):
        // /integration/product/product-categories

        $path = 'https://apigw.trendyol.com/integration/product/product-categories';

        $response = $this->request( 'GET', $path );

        $category_url = $path;

        error_log( 'WR TRENDYOL DEBUG: CATEGORY URL (FIXED: apigw.trendyol.com) => ' . $category_url );

        error_log( 'WR TRENDYOL DEBUG: CATEGORY RAW RESPONSE => ' . print_r( $response, true ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'WR TRENDYOL DEBUG: CATEGORY ERROR => ' . $response->get_error_message() );
            return $response;
        }

        $body = isset( $response['body'] ) ? $response['body'] : array();
        $categories = isset( $body['categories'] ) ? $body['categories'] : $body;

        if ( ! is_array( $categories ) ) {
            $categories = array();
        }

        set_transient( 'wr_trendyol_categories', $categories, WEEK_IN_SECONDS );

        return $categories;
    }

    /**
     * Belirli bir kategori için attribute listesi
     *
     * @param int $category_id
     *
     * @return array|WP_Error
     */
    public function get_category_attributes( $category_id ) {

        $category_id = absint( $category_id );
        if ( ! $category_id ) {
            return $this->wrap_error( 'invalid_category', 'Geçersiz kategori ID.' );
        }

        $cache_key = 'wr_trendyol_cat_attrs_' . $category_id;
        $cached    = get_transient( $cache_key );

        if ( $cached !== false ) {
            return $cached;
        }

        // NEW OFFICIAL ENDPOINT:
        // /integration/product/product-categories/{categoryId}/attributes

        $path = sprintf( 'https://apigw.trendyol.com/integration/product/product-categories/%d/attributes', $category_id );
        $response = $this->request( 'GET', $path );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = isset( $response['body'] ) ? $response['body'] : array();

        // Trendyol yeni attribute formatı
        $attrs = [];

        if ( isset( $body['categoryAttributes'] ) && is_array( $body['categoryAttributes'] ) ) {
            $attrs = $body['categoryAttributes'];
        }
        // Eski fallback (bazı seller hesaplarında farklı dönebiliyor)
        elseif ( isset( $body['attributes'] ) && is_array( $body['attributes'] ) ) {
            $attrs = $body['attributes'];
        }

        // Her ihtimale karşı array değilse boş yap
        if ( ! is_array( $attrs ) ) {
            $attrs = [];
        }

        set_transient( $cache_key, $attrs, HOUR_IN_SECONDS );

        return $attrs;
    }

    /**
     * Whether debug mode is enabled.
     *
     * @return bool
     */
    public function is_debug_enabled() {
        return $this->debug;
    }

    /**
     * Wrap a WP_Error with optional debug payload.
     *
     * @param string $code    Error code suffix.
     * @param string $message Error message.
     * @param array  $data    Additional data.
     *
     * @return WP_Error
     */
    protected function wrap_error( $code, $message, $data = [] ) {
        if ( ! $this->debug ) {
            return new WP_Error( 'wr_trendyol_' . $code, $message );
        }

        return new WP_Error( 'wr_trendyol_' . $code, $message, $data );
    }
}
