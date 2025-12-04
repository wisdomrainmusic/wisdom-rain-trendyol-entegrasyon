<?php
namespace WR\Trendyol;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WR_Trendyol_API_Client {

    protected $settings = [];
    protected $seller_id = '';
    protected $api_key = '';
    protected $api_secret = '';
    protected $environment = WR_Trendyol_Plugin::ENV_PROD;
    protected $user_agent = '';
    protected $debug = false;

    /**
     * Trendyol yeni gateway – ürün gönderimi için zorunlu SAPIGW adresi
     */
    protected $environments = [
        WR_Trendyol_Plugin::ENV_PROD    => 'https://api.trendyol.com/sapigw/',
        WR_Trendyol_Plugin::ENV_SANDBOX => 'https://api.trendyol.com/sapigw/',
    ];

    public function __construct( array $settings ) {
        $this->settings    = $settings;
        $this->seller_id   = isset( $settings['seller_id'] ) ? trim( (string) $settings['seller_id'] ) : '';
        $this->api_key     = isset( $settings['api_key'] ) ? trim( (string) $settings['api_key'] ) : '';
        $this->api_secret  = isset( $settings['api_secret'] ) ? trim( (string) $settings['api_secret'] ) : '';
        $this->environment = isset( $settings['environment'] ) && WR_Trendyol_Plugin::ENV_SANDBOX === $settings['environment'] ? WR_Trendyol_Plugin::ENV_SANDBOX : WR_Trendyol_Plugin::ENV_PROD;
        $this->debug       = ! empty( $settings['debug'] );

        $user_agent       = isset( $settings['user_agent'] ) ? trim( (string) $settings['user_agent'] ) : '';
        $this->user_agent = '' !== $user_agent ? $user_agent : $this->get_default_user_agent();
    }

    public function get_base_url() {
        if ( ! isset( $this->environments[ $this->environment ] ) ) {
            $this->environment = WR_Trendyol_Plugin::ENV_PROD;
        }
        return trailingslashit( $this->environments[ $this->environment ] );
    }

    protected function get_default_user_agent() {
        if ( ! empty( $this->seller_id ) ) {
            return sprintf( '%s - Self Integration', $this->seller_id );
        }
        return 'WisdomRain-Trendyol-WooCommerce/' . WR_TRENDYOL_PLUGIN_VERSION;
    }

    protected function build_auth_header() {
        return 'Basic ' . base64_encode( $this->api_key . ':' . $this->api_secret );
    }

    protected function get_base_headers() {
        return [
            'Authorization' => $this->build_auth_header(),
            'User-Agent'    => $this->user_agent,
            'Accept'        => 'application/json',
        ];
    }

    protected function log_debug( $message, array $context = [] ) {
        if ( ! $this->debug ) {
            return;
        }
        $suffix = empty( $context ) ? '' : ' => ' . print_r( $context, true );
        error_log( 'WR TRENDYOL DEBUG: ' . $message . $suffix );
    }

    protected function format_error_message( $decoded, $status, $status_message, $raw_body ) {
        $pieces = [];

        if ( $status ) {
            $pieces[] = sprintf( 'HTTP %d%s', $status, $status_message ? ' ' . $status_message : '' );
        }

        if ( is_array( $decoded ) ) {
            foreach ( [ 'errorMessage', 'message', 'error', 'description' ] as $field ) {
                if ( ! empty( $decoded[ $field ] ) ) {
                    $pieces[] = $decoded[ $field ];
                    break;
                }
            }

            if ( isset( $decoded['errors'] ) && is_array( $decoded['errors'] ) ) {
                $err_txt = [];
                foreach ( $decoded['errors'] as $err ) {
                    if ( is_array( $err ) ) {
                        $err_txt[] = implode( ' - ', array_filter( [ $err['code'] ?? '', $err['message'] ?? '' ] ) );
                    } elseif ( is_string( $err ) ) {
                        $err_txt[] = $err;
                    }
                }
                if ( $err_txt ) $pieces[] = implode( '; ', $err_txt );
            }

        } elseif ( is_string( $raw_body ) && $raw_body !== '' ) {
            $pieces[] = $raw_body;
        }

        if ( empty( $pieces ) ) {
            $pieces[] = __( 'Trendyol API error', 'wisdom-rain-trendyol-entegrasyon' );
        }

        return implode( ' | ', array_filter( $pieces ) );
    }

    public function request( $method, $path, $args = [] ) {

        if ( empty( $this->api_key ) || empty( $this->api_secret ) ) {
            return new WP_Error( 'wr_trendyol_missing_credentials', 'API key ve secret gerekli.' );
        }

        $is_absolute = (bool) parse_url( $path, PHP_URL_SCHEME );
        $url = $is_absolute ? $path : $this->get_base_url() . ltrim( $path, '/' );

        if ( ! empty( $args['query'] ) ) {
            $url = add_query_arg( $args['query'], $url );
        }

        $headers = $this->get_base_headers();
        if ( ! empty( $args['headers'] ) ) {
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
            $request_args['body'] = wp_json_encode( $args['body'] );
            $request_args['headers']['Content-Type'] = 'application/json';
        }

        $response = wp_remote_request( $url, $request_args );

        if ( is_wp_error( $response ) ) {
            return $this->wrap_error( 'http_request_failed', $response->get_error_message(), [
                'url' => $url
            ]);
        }

        $status  = wp_remote_retrieve_response_code( $response );
        $msg     = wp_remote_retrieve_response_message( $response );
        $raw     = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $raw, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $decoded = null;
        }

        if ( $status >= 400 ) {

            $message = $this->format_error_message( $decoded, $status, $msg, $raw );

            return $this->wrap_error(
                'trendyol_api_error',
                $message,
                [
                    'status'  => $status,
                    'body'    => $decoded ?? $raw,
                    'url'     => $url,
                    'raw'     => $raw,
                ]
            );
        }

        return [
            'status' => $status,
            'body'   => $decoded ?? $raw,
            'raw'    => $this->debug ? $response : null,
        ];
    }

    /**
     * Sağlık kontrolü
     */
    public function health_check() {
        $path = sprintf( 'integration/sellers/%s/addresses', $this->seller_id );
        return $this->request( 'GET', $path );
    }

    /**
     * 🚀 ÜRÜN GÖNDERİM ENDPOINT — DOĞRU HALİ
     */
    public function get_products_path() {
        return sprintf( 'suppliers/%s/products', $this->seller_id );
    }

    /**
     * Kategoriler
     */
    public function get_categories() {

        $cached = get_transient( 'wr_trendyol_categories' );
        if ( $cached !== false ) return $cached;

        $path = 'https://api.trendyol.com/sapigw/integration/product/product-categories';

        $response = $this->request( 'GET', $path );

        if ( is_wp_error( $response ) ) return $response;

        $body = $response['body'] ?? [];
        $cats = $body['categories'] ?? $body;

        if ( ! is_array( $cats ) ) $cats = [];

        set_transient( 'wr_trendyol_categories', $cats, WEEK_IN_SECONDS );

        return $cats;
    }

    /**
     * Attribute endpoint (varsın aynı kalsın)
     */
    private function wr_normalize_attribute_payload( $body ) {
        if ( ! is_array( $body ) ) return [];

        if ( isset( $body['categoryAttributes'] ) ) return $body['categoryAttributes'];
        if ( isset( $body['attributes'] ) ) return $body['attributes'];
        if ( isset( $body['result'] ) ) return $this->wr_normalize_attribute_payload( $body['result'] );

        foreach ( $body as $value ) {
            if ( is_array( $value ) ) {
                $ret = $this->wr_normalize_attribute_payload( $value );
                if ( $ret ) return $ret;
            }
        }
        return [];
    }

    public function get_category_attributes( $category_id ) {

        $category_id = absint( $category_id );
        if ( ! $category_id ) {
            return new WP_Error( 'invalid_category', 'Geçersiz kategori.' );
        }

        $cache_key = 'wr_trendyol_cat_attrs_' . $category_id;
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) return $cached;

        $seller = $this->seller_id;
        $path   = "integration/ecgw/v1/{$seller}/lookup/product-categories/{$category_id}/attributes";

        $response = $this->request( 'GET', $path );

        if ( is_wp_error( $response ) ) return $response;

        $body = $response['body'];

        $decoded = is_array( $body ) ? $body : json_decode( $body, true );

        $attrs = $this->wr_normalize_attribute_payload( $decoded );

        if ( empty( $attrs ) ) {
            delete_transient( $cache_key );
            return new WP_Error( 'attr_empty', 'Trendyol boş attribute döndü.' );
        }

        set_transient( $cache_key, $attrs, HOUR_IN_SECONDS );

        return $attrs;
    }

    public function is_debug_enabled() {
        return $this->debug;
    }

    protected function wrap_error( $code, $message, $data = [] ) {
        if ( ! $this->debug ) return new WP_Error( 'wr_trendyol_' . $code, $message );
        return new WP_Error( 'wr_trendyol_' . $code, $message, $data );
    }
}
