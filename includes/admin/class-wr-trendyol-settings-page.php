<?php
namespace WR\Trendyol\Admin;

use WR\Trendyol\WR_Trendyol_Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WR_Trendyol_Settings_Page {
    /**
     * Main plugin instance.
     *
     * @var WR_Trendyol_Plugin
     */
    protected $plugin;

    /**
     * Constructor.
     *
     * @param WR_Trendyol_Plugin $plugin Plugin instance.
     */
    public function __construct( WR_Trendyol_Plugin $plugin ) {
        $this->plugin = $plugin;

        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_post_wr_trendyol_test_connection', [ $this, 'handle_health_check' ] );
        add_action( 'admin_notices', [ $this, 'render_notices' ] );
    }

    /**
     * Add settings page under WooCommerce.
     */
    public function add_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Trendyol Entegrasyon', 'wisdom-rain-trendyol-entegrasyon' ),
            __( 'Trendyol Entegrasyon', 'wisdom-rain-trendyol-entegrasyon' ),
            'manage_woocommerce',
            'wr-trendyol-settings',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Register settings and sanitization callback.
     */
    public function register_settings() {
        register_setting( 'wr_trendyol_settings_group', WR_Trendyol_Plugin::OPTION_KEY, [ $this, 'sanitize_settings' ] );
    }

    /**
     * Sanitize settings input.
     *
     * @param array $input Raw input.
     *
     * @return array
     */
    public function sanitize_settings( $input ) {
        $sanitized = [];

        $sanitized['seller_id']  = isset( $input['seller_id'] ) ? sanitize_text_field( wp_unslash( $input['seller_id'] ) ) : '';
        $sanitized['api_key']    = isset( $input['api_key'] ) ? sanitize_text_field( wp_unslash( $input['api_key'] ) ) : '';
        $sanitized['api_secret'] = isset( $input['api_secret'] ) ? sanitize_text_field( wp_unslash( $input['api_secret'] ) ) : '';
        $sanitized['user_agent'] = isset( $input['user_agent'] ) ? sanitize_text_field( wp_unslash( $input['user_agent'] ) ) : '';

        $environment             = isset( $input['environment'] ) ? sanitize_text_field( wp_unslash( $input['environment'] ) ) : WR_Trendyol_Plugin::ENV_PROD;
        $allowed_environments    = [ WR_Trendyol_Plugin::ENV_PROD, WR_Trendyol_Plugin::ENV_SANDBOX ];
        $sanitized['environment'] = in_array( $environment, $allowed_environments, true ) ? $environment : WR_Trendyol_Plugin::ENV_PROD;

        if ( '' === $sanitized['user_agent'] ) {
            $sanitized['user_agent'] = $this->plugin->get_default_user_agent( $sanitized['seller_id'] );
        }

        $sanitized['debug'] = ! empty( $input['debug'] ) ? 1 : 0;

        return $sanitized;
    }

    /**
     * Render settings page.
     */
    public function render_page() {
        $settings = $this->plugin->get_settings();
        $test_url = wp_nonce_url(
            admin_url( 'admin-post.php?action=wr_trendyol_test_connection' ),
            'wr_trendyol_test_connection',
            '_wr_trendyol_nonce'
        );

        include WR_TRENDYOL_PLUGIN_PATH . 'includes/admin/views/settings-page.php';
    }

    /**
     * Handle health check submissions.
     */
    public function handle_health_check() {
        check_admin_referer( 'wr_trendyol_test_connection', '_wr_trendyol_nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'wisdom-rain-trendyol-entegrasyon' ) );
        }

        $client = $this->plugin->get_api_client();
        $result = $client->health_check();

        if ( is_wp_error( $result ) ) {
            $message = $result->get_error_message();

            if ( $client->is_debug_enabled() && ! empty( $result->get_error_data() ) ) {
                $message .= ' (' . wp_json_encode( $result->get_error_data() ) . ')';
            }

            $this->add_notice( $message, 'error' );
        } else {
            $status = isset( $result['status'] ) ? (int) $result['status'] : 200;
            $this->add_notice(
                sprintf(
                    /* translators: %d: HTTP status code */
                    __( 'Health check succeeded. Trendyol API is reachable. (HTTP %d)', 'wisdom-rain-trendyol-entegrasyon' ),
                    $status
                ),
                'success'
            );
        }

        wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wr-trendyol-settings' ) );
        exit;
    }

    /**
     * Store admin notices via transient.
     *
     * @param string $message Notice message.
     * @param string $type    Notice type.
     */
    protected function add_notice( $message, $type = 'success' ) {
        $notices   = get_transient( 'wr_trendyol_notices' );
        $notices   = is_array( $notices ) ? $notices : [];
        $notices[] = [
            'message' => $message,
            'type'    => $type,
        ];

        set_transient( 'wr_trendyol_notices', $notices, MINUTE_IN_SECONDS * 5 );
    }

    /**
     * Render notices stored in transient.
     */
    public function render_notices() {
        $notices = get_transient( 'wr_trendyol_notices' );

        if ( empty( $notices ) || ! is_array( $notices ) ) {
            return;
        }

        delete_transient( 'wr_trendyol_notices' );

        foreach ( $notices as $notice ) {
            $type    = isset( $notice['type'] ) ? $notice['type'] : 'success';
            $message = isset( $notice['message'] ) ? $notice['message'] : '';

            printf(
                '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
                esc_attr( $type ),
                esc_html( $message )
            );
        }
    }
}
