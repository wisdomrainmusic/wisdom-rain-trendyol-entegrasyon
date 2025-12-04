<?php
namespace WR\Trendyol;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

class WR_Trendyol_API_Client {

    protected $settings = [];
    protected $seller_id = '';
    protected $api_key = '';
    protected $api_secret = '';
    protected $environment = WR_Trendyol_Plugin::ENV_PROD;
    protected $user_agent = '';
    protected $debug = false;

    /**
     * Trendyol SAPIGW – zorunlu host
     */
    protected $environments = [
        WR_Trendyol_Plugin::ENV_PROD    => 'https://api.trendyol.com/sapigw/',
        WR_Trendyol_Plugin::ENV_SANDBOX => 'https://api.trendyol.com/sapigw/',
    ];

    public function __construct( array $settings ) {
        $this->settings    = $settings;
        $this->seller_id   = $settings['seller_id']  ?? '';
        $this->api_key     = $settings['api_key']    ?? '';
        $this->api_secret  = $settings['api_secret'] ?? '';
        $this->environment = WR_Trendyol_Plugin::ENV_PROD;
        $this->debug       = ! empty( $settings['debug'] );

        $ua = $settings['user_agent'] ?? '';
        $this->user_agent = $ua !== '' ? $ua : $this->get_default_user_agent();
    }

    /**
     * 🧩 EX MISSING → Mapper için GEREKLİ!
     */
    public function get_settings() {
        return $this->settings;
    }

    public function get_base_url() {
        return trailingslashit( $this->environments[$this->environment] );
    }

    protected function get_default_user_agent() {
        if ( ! empty( $this->seller_id ) ) {
            return "{$this->seller_id} - Self Integration";
        }
        return 'WisdomRain-Trendyol-WooCommerce/' . WR_TRENDYOL_PLUGIN_VERSION;
    }

    protected function build_auth_header() {
        return 'Basic ' . base64_encode( "{$this->api_key}:{$this->api_secret}" );
    }

    protected function get_base_headers() {
        return [
            'Authorization' => $this->build_auth_header(),
            'User-Agent'    => $this->user_agent,
            'Accept'        => 'application/json',
        ];
    }

    protected function log_debug( $m, $ctx = [] ) {
        if ( ! $this->debug ) return;
        error_log( "WR TRENDYOL DEBUG: $m " . (!empty($ctx) ? print_r($ctx, true) : '') );
    }

    protected function format_error_message( $decoded, $status, $status_message, $raw_body ) {

        $out = [];

        if ( $status ) {
            $out[] = "HTTP {$status}" . ($status_message ? " {$status_message}" : '');
        }

        if ( is_array( $decoded ) ) {

            foreach ( ['errorMessage','message','error','description'] as $f ) {
                if ( ! empty( $decoded[$f] ) ) {
                    $out[] = $decoded[$f];
                    break;
                }
            }

            if ( isset( $decoded['errors'] ) ) {
                $tmp = [];
                foreach ( $decoded['errors'] as $e ) {
                    if ( is_array($e) ) {
                        $tmp[] = implode(' - ', array_filter([$e['code'] ?? '', $e['message'] ?? '']));
                    } elseif ( is_string($e) ) {
                        $tmp[] = $e;
                    }
                }
                if ( $tmp ) $out[] = implode('; ', $tmp);
            }

        } elseif ( is_string( $raw_body ) ) {
            $out[] = $raw_body;
        }

        if ( empty( $out ) ) {
            $out[] = 'Trendyol API error';
        }

        return implode(' | ', array_filter($out));
    }

    /**
     * 🔥 MERKEZ – TÜM API İSTEKLERİ BURADAN GEÇER
     */
    public function request( $method, $path, $args = [] ) {

        if ( empty( $this->api_key ) || empty( $this->api_secret ) ) {
            return new WP_Error('wr_trendyol_missing_credentials', 'API key ve secret gerekli.');
        }

        $is_absolute = (bool) parse_url($path, PHP_URL_SCHEME);
        $url = $is_absolute ? $path : $this->get_base_url() . ltrim($path, '/');

        if ( ! empty($args['query']) ) {
            $url = add_query_arg($args['query'], $url);
        }

        $headers = $this->get_base_headers();
        if ( ! empty($args['headers']) ) {
            $headers = array_merge($headers, $args['headers']);
        }

        $request_args = [
            'method'    => strtoupper($method),
            'headers'   => $headers,
            'timeout'   => $args['timeout'] ?? 30,
            'sslverify' => true,
        ];

        if ( ! empty($args['body']) ) {
            $request_args['body'] = wp_json_encode($args['body']);
            $request_args['headers']['Content-Type'] = 'application/json';
        }

        $response = wp_remote_request($url, $request_args);

        if ( is_wp_error($response) ) {
            return $this->wrap_error('http_request_failed', $response->get_error_message(), [
                'url' => $url
            ]);
        }

        $status = wp_remote_retrieve_response_code($response);
        $msg    = wp_remote_retrieve_response_message($response);
        $raw    = wp_remote_retrieve_body($response);

        $decoded = json_decode($raw, true);
        if ( json_last_error() !== JSON_ERROR_NONE ) $decoded = null;

        if ( $status >= 400 ) {

            $message = $this->format_error_message($decoded, $status, $msg, $raw);

            return $this->wrap_error('trendyol_api_error', $message, [
                'status' => $status,
                'body'   => $decoded ?? $raw,
                'url'    => $url,
                'raw'    => $raw,
            ]);
        }

        return [
            'status' => $status,
            'body'   => $decoded ?? $raw,
            'raw'    => $this->debug ? $response : null
        ];
    }

    /**
     * 🟢 Sağlık kontrolü
     */
    public function health_check() {
        return $this->request('GET', "integration/sellers/{$this->seller_id}/addresses");
    }

    /**
     * 🚀 ÜRÜN GÖNDERİM ENDPOINT – DOĞRU FORMAT
     */
    public function get_products_path() {
        return "suppliers/{$this->seller_id}/products";
    }

    /**
     * 📚 Kategori listesi – Yeni APIGW
     */
    public function get_categories() {

        if ( $cached = get_transient('wr_trendyol_categories') )
            return $cached;

        $path = 'https://api.trendyol.com/sapigw/integration/product/product-categories';
        $res  = $this->request('GET', $path);

        if ( is_wp_error($res) ) return $res;

        $body = $res['body'] ?? [];
        $cats = $body['categories'] ?? $body;

        if ( ! is_array($cats) ) $cats = [];

        set_transient('wr_trendyol_categories', $cats, WEEK_IN_SECONDS);

        return $cats;
    }

    /**
     * 🧬 Attribute normalize
     */
    private function wr_normalize_attribute_payload( $b ) {
        if ( ! is_array($b) ) return [];

        if ( isset($b['categoryAttributes']) ) return $b['categoryAttributes'];
        if ( isset($b['attributes']) ) return $b['attributes'];
        if ( isset($b['result']) ) return $this->wr_normalize_attribute_payload($b['result']);

        foreach ( $b as $v ) {
            if ( is_array($v) ) {
                $r = $this->wr_normalize_attribute_payload($v);
                if ( $r ) return $r;
            }
        }
        return [];
    }

    /**
     * ⚙️ Attribute Fetch
     */
    public function get_category_attributes( $category_id ) {

        $category_id = absint($category_id);
        if ( ! $category_id ) {
            return new WP_Error('invalid_category', 'Geçersiz kategori.');
        }

        $cache_key = "wr_trendyol_cat_attrs_$category_id";
        if ( $cached = get_transient($cache_key) )
            return $cached;

        $path = "integration/ecgw/v1/{$this->seller_id}/lookup/product-categories/{$category_id}/attributes";

        $res  = $this->request('GET', $path);

        if ( is_wp_error($res) ) return $res;

        $body    = $res['body'];
        $decoded = is_array($body) ? $body : json_decode($body, true);

        $attrs = $this->wr_normalize_attribute_payload($decoded);

        if ( empty($attrs) ) {
            delete_transient($cache_key);
            return new WP_Error('attr_empty', 'Trendyol boş attribute döndürdü.');
        }

        set_transient($cache_key, $attrs, HOUR_IN_SECONDS);

        return $attrs;
    }

    public function is_debug_enabled() {
        return $this->debug;
    }

    protected function wrap_error( $c, $m, $d = [] ) {
        if ( ! $this->debug ) return new WP_Error("wr_trendyol_$c", $m);
        return new WP_Error("wr_trendyol_$c", $m, $d);
    }
}
