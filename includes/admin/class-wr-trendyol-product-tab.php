<?php
namespace WR\Trendyol\Admin;

use WR\Trendyol\WR_Trendyol_Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WooCommerce ürün düzenleme ekranına "Trendyol Entegrasyon" tab'ı ekler.
 */
class WR_Trendyol_Product_Tab {

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

        add_filter( 'woocommerce_product_data_tabs', [ $this, 'add_product_tab' ] );
        add_action( 'woocommerce_product_data_panels', [ $this, 'render_product_panel' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_meta' ], 20, 2 );

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // AJAX: kategoriye göre attribute yükleme (tekil kayıt olacak şekilde)
        if ( ! has_action( 'wp_ajax_wr_trendyol_load_attributes', [ $this, 'ajax_load_attributes' ] ) ) {
            add_action( 'wp_ajax_wr_trendyol_load_attributes', [ $this, 'ajax_load_attributes' ] );
        }
        // AJAX: ürünü Trendyol'a gönder
        add_action( 'wp_ajax_wr_trendyol_push_product', [ $this, 'ajax_push_product' ] );
    }

    /**
     * Admin assetlerini yükle.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        $screen = get_current_screen();

        if ( empty( $screen ) ) {
            return;
        }

        // Product edit screen değilse çık
        if ( $screen->post_type !== 'product' ) {
            return;
        }

        // Not: WPBakery / Divi / block-editor fark etmeden JS burada yüklenir

        wp_enqueue_script(
            'wr-trendyol-admin-product',
            WR_TRENDYOL_PLUGIN_URL . 'assets/js/wr-trendyol-admin-product.js',
            [ 'jquery' ],
            WR_TRENDYOL_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'wr-trendyol-admin-product',
            'wrTrendyolProduct',
            [
                'ajax_url'             => admin_url( 'admin-ajax.php' ),
                'nonce'                => wp_create_nonce( 'wr_trendyol_product_nonce' ),
                'missing_category_msg' => __( 'Lütfen önce Trendyol kategorisi seçin.', 'wisdom-rain-trendyol-entegrasyon' ),
                'push_success_msg'     => __( "Ürün Trendyol'a gönderildi.", 'wisdom-rain-trendyol-entegrasyon' ),
                'push_error_msg'       => __( 'Ürün gönderilirken hata oluştu. Ayrıntı için hataları kontrol edin.', 'wisdom-rain-trendyol-entegrasyon' ),
            ]
        );

        wp_add_inline_script(
            'wr-trendyol-admin-product',
            'window.WRTrendyolProduct = window.wrTrendyolProduct;',
            'after'
        );
    }

    /**
     * Yeni tab başlığı.
     *
     * @param array $tabs Existing tabs.
     *
     * @return array
     */
    public function add_product_tab( $tabs ) {
        $tabs['wr_trendyol'] = [
            'label'    => __( 'Trendyol Entegrasyon', 'wisdom-rain-trendyol-entegrasyon' ),
            'target'   => 'wr_trendyol_product_data',
            'class'    => [],
            'priority' => 80,
        ];

        return $tabs;
    }

    /**
     * Tab içeriği.
     */
    public function render_product_panel() {

        // KATEGORİLERİ OTOMATİK ÇEK.
        $client     = $this->plugin->get_api_client();
        $categories = $client->get_categories();

        if ( is_wp_error( $categories ) ) {
            error_log( 'WR TRENDYOL DEBUG: AUTO FETCH FAILED => ' . $categories->get_error_message() );
        } else {
            error_log( 'WR TRENDYOL DEBUG: AUTO FETCH SUCCESS, count=' . count( $categories ) );
        }

        // Devam eden orijinal kod:
        global $post;

        $product_id = $post->ID;

        $category_id        = get_post_meta( $product_id, '_trendyol_category_id', true );
        if ( ! $category_id ) {
            $category_id = get_post_meta( $product_id, '_wr_trendyol_category_id', true );
        }
        $brand_id           = get_post_meta( $product_id, '_wr_trendyol_brand_id', true );
        $barcode            = get_post_meta( $product_id, '_wr_trendyol_barcode', true );
        $dimensional_weight = get_post_meta( $product_id, '_wr_trendyol_dimensional_weight', true );
        $enabled            = 'yes' === get_post_meta( $product_id, '_wr_trendyol_enabled', true );
        $product_tid        = get_post_meta( $product_id, '_wr_trendyol_product_id', true );

        ?>
        <div id="wr_trendyol_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label for="wr_trendyol_category"><?php _e( 'Trendyol Category', 'wisdom-rain-trendyol-entegrasyon' ); ?></label>
                    <?php
                    $this->render_category_dropdown( $product_id );
                    ?>
                    <span class="description">
                        <?php _e( 'Trendyol category selection for this product.', 'wisdom-rain-trendyol-entegrasyon' ); ?>
                    </span>
                </p>

                <p class="form-field">
                    <label for="wr_trendyol_brand_id"><?php _e( 'Trendyol Brand ID', 'wisdom-rain-trendyol-entegrasyon' ); ?></label>
                    <input type="text"
                           id="wr_trendyol_brand_id"
                           name="wr_trendyol_brand_id"
                           value="<?php echo esc_attr( $brand_id ); ?>"
                           placeholder="<?php esc_attr_e( 'Örn: 1234', 'wisdom-rain-trendyol-entegrasyon' ); ?>" />
                    <span class="description">
                        <?php _e( 'Trendyol marka ID. İleride brand listesi ile dropdown yapılabilir.', 'wisdom-rain-trendyol-entegrasyon' ); ?>
                    </span>
                </p>

                <p class="form-field">
                    <label for="wr_trendyol_barcode"><?php _e( 'Barcode', 'wisdom-rain-trendyol-entegrasyon' ); ?></label>
                    <input type="text"
                           id="wr_trendyol_barcode"
                           name="wr_trendyol_barcode"
                           value="<?php echo esc_attr( $barcode ); ?>"
                           placeholder="<?php esc_attr_e( 'EAN / barkod', 'wisdom-rain-trendyol-entegrasyon' ); ?>" />
                    <span class="description">
                        <?php _e( 'Zorunlu alan. Boş bırakılırsa otomatik üretilebilir, Trendyol kurallarına göre.', 'wisdom-rain-trendyol-entegrasyon' ); ?>
                    </span>
                </p>

                <p class="form-field">
                    <label for="wr_trendyol_dimensional_weight"><?php _e( 'Dimensional Weight', 'wisdom-rain-trendyol-entegrasyon' ); ?></label>
                    <input type="number"
                           step="0.01"
                           min="0"
                           id="wr_trendyol_dimensional_weight"
                           name="wr_trendyol_dimensional_weight"
                           value="<?php echo esc_attr( $dimensional_weight ); ?>" />
                    <span class="description">
                        <?php _e( 'Kargo hacimsel ağırlık.', 'wisdom-rain-trendyol-entegrasyon' ); ?>
                    </span>
                </p>

                <p class="form-field">
                    <label for="wr_trendyol_enabled">
                        <input type="checkbox"
                               id="wr_trendyol_enabled"
                               name="wr_trendyol_enabled"
                               value="yes" <?php checked( $enabled ); ?> />
                        <?php _e( 'Bu ürünü Trendyol’a senkronize et', 'wisdom-rain-trendyol-entegrasyon' ); ?>
                    </label>
                </p>

                <?php if ( $product_tid ) : ?>
                    <p class="form-field">
                        <strong><?php _e( 'Trendyol Product ID:', 'wisdom-rain-trendyol-entegrasyon' ); ?></strong>
                        <?php echo esc_html( $product_tid ); ?>
                    </p>
                <?php endif; ?>

            </div>

            <div class="options_group">
                <h3><?php _e( 'Kategoriye Özel Zorunlu Özellikler', 'wisdom-rain-trendyol-entegrasyon' ); ?></h3>
                <p class="description">
                    <?php _e( 'Kategori seçtikten sonra "Özellikleri Yükle" butonuna basın. Zorunlu alanlar otomatik gelir.', 'wisdom-rain-trendyol-entegrasyon' ); ?>
                </p>

                <p>
                    <button type="button"
                            class="button"
                            id="wr_trendyol_load_attributes_btn">
                        <?php _e( 'Özellikleri Yükle', 'wisdom-rain-trendyol-entegrasyon' ); ?>
                    </button>
                </p>

                <div id="wr_trendyol_attributes_box" style="padding:10px; background:#fff; border:1px solid #ddd;">
                    <?php echo esc_html__( 'Kategori seçin ve "Özellikleri Yükle" butonuna basın.', 'wisdom-rain-trendyol-entegrasyon' ); ?>

                    <div id="wr_trendyol_attributes_wrap">
                        <?php
                        // Sayfa load edildiğinde mevcut attribute meta'larını göster (AJAX sonrası da aynı HTML kullanılacak)
                        $this->render_attributes_fields_static( $product_id, $category_id );
                        ?>
                    </div>
                </div>
            </div>

            <div class="options_group">
                <h3><?php _e( 'Trendyol Ürün Gönderimi', 'wisdom-rain-trendyol-entegrasyon' ); ?></h3>
                <p class="description">
                    <?php _e( "Kaydettikten sonra bu buton ile ürünü Trendyol'a gönderebilirsiniz.", 'wisdom-rain-trendyol-entegrasyon' ); ?>
                </p>
                <p>
                    <button type="button"
                            class="button button-primary"
                            id="wr_trendyol_push_product_btn"
                            data-product-id="<?php echo esc_attr( $product_id ); ?>">
                        <?php _e( "Ürünü Trendyol'a Gönder", 'wisdom-rain-trendyol-entegrasyon' ); ?>
                    </button>
                    <span id="wr_trendyol_push_result" style="margin-left:8px;"></span>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Render Trendyol Category Dropdown (with data-category-id).
     *
     * @param int $post_id Product ID.
     */
    public function render_category_dropdown( $post_id ) {
        if ( ! class_exists( '\\WR_Trendyol_Categories' ) ) {
            echo '<p class="notice notice-error">Trendyol kategori yöneticisi bulunamadı.</p>';
            return;
        }

        $options = \WR_Trendyol_Categories::get_flat_options();

        if ( empty( $options ) ) {
            echo '<p class="notice notice-error">Trendyol kategori listesi yüklenemedi. Lütfen ayarları kontrol edin.</p>';
            return;
        }

        $selected_id = get_post_meta( $post_id, '_wr_trendyol_category_id', true );
        ?>

        <select id="wr_trendyol_category_select"
                name="wr_trendyol_category_select"
                style="min-width: 280px;">
            <option value=""><?php esc_html_e( 'Bir Trendyol kategorisi seçin', 'wr-trendyol' ); ?></option>

            <?php foreach ( $options as $id => $label ) : ?>
                <option value="<?php echo esc_attr( $id ); ?>"
                    <?php selected( (int) $selected_id, (int) $id ); ?>
                    data-category-id="<?php echo esc_attr( $id ); ?>">
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <input type="hidden"
               id="wr_trendyol_category_id"
               name="wr_trendyol_category_id"
               value="<?php echo esc_attr( $selected_id ); ?>" />

        <?php
    }

    /**
     * Kategori attributes için statik render (mevcut kaydedilmiş değerler).
     *
     * @param int $product_id  Product ID.
     * @param int $category_id Trendyol category ID.
     */
    protected function render_attributes_fields_static( $product_id, $category_id, $preloaded_attrs = null ) {
        $category_id = (int) $category_id;

        if ( ! $category_id ) {
            echo '<p>' . esc_html__( 'Henüz kategori seçilmedi.', 'wisdom-rain-trendyol-entegrasyon' ) . '</p>';
            return;
        }

        $attrs = is_array( $preloaded_attrs ) ? $preloaded_attrs : null;

        if ( null === $attrs ) {
            $client = $this->plugin->get_api_client();
            $attrs  = $client->get_category_attributes( $category_id );
        }

        if ( is_wp_error( $attrs ) || empty( $attrs ) ) {
            echo '<p>' . esc_html__( 'Bu kategori için attribute bilgisi alınamadı.', 'wisdom-rain-trendyol-entegrasyon' ) . '</p>';
            return;
        }

        foreach ( $attrs as $attr ) {
            $attr_id      = isset( $attr['attributeId'] ) ? (int) $attr['attributeId'] : ( isset( $attr['id'] ) ? (int) $attr['id'] : 0 );
            $name         = isset( $attr['attributeName'] ) ? $attr['attributeName'] : ( isset( $attr['name'] ) ? $attr['name'] : '' );
            $required     = ! empty( $attr['required'] ) || ! empty( $attr['mandatory'] );
            $values       = isset( $attr['attributeValues'] ) ? $attr['attributeValues'] : ( isset( $attr['values'] ) ? $attr['values'] : [] );
            $multiple     = ! empty( $attr['multipleValues'] );
            $allow_custom = ! empty( $attr['customValue'] ) || ! empty( $attr['allowCustom'] );

            if ( ! $attr_id || ! $name ) {
                continue;
            }

            $meta_key      = '_wr_trendyol_attr_' . $attr_id;
            $stored        = get_post_meta( $product_id, $meta_key, true );
            $stored_id     = [];
            $stored_custom = '';

            if ( is_array( $stored ) ) {
                $stored_id     = isset( $stored['value_id'] ) ? (array) $stored['value_id'] : [];
                $stored_custom = isset( $stored['custom'] ) ? $stored['custom'] : '';
            }

            echo '<p class="form-field">';
            echo '<label>' . esc_html( $name );
            if ( $required ) {
                echo ' <span style="color:#d63638;">*</span>';
            }
            echo '</label>';

            if ( ! empty( $values ) && is_array( $values ) ) {

                // MULTIPLE SELECT destekle
                $multiple_attr = $multiple ? ' multiple="multiple"' : '';

                echo '<select name="wr_trendyol_attr[' . esc_attr( $attr_id ) . '][value_id][]" 
                             class="wc-enhanced-select" style="max-width:260px;"' . $multiple_attr . '>';

                echo '<option value="">' . esc_html__( 'Seçin…', 'wisdom-rain-trendyol-entegrasyon' ) . '</option>';

                foreach ( $values as $val ) {
                    $vid   = isset( $val['attributeValueId'] ) ? $val['attributeValueId'] : ( isset( $val['id'] ) ? $val['id'] : 0 );
                    $vname = isset( $val['attributeValue'] ) ? $val['attributeValue'] : ( isset( $val['name'] ) ? $val['name'] : '' );
                    if ( ! $vid ) continue;

                    $selected = selected( true, in_array( (int) $vid, array_map( 'intval', $stored_id ), true ), false );

                    printf(
                        '<option value="%d"%s>%s</option>',
                        (int) $vid,
                        $selected,
                        esc_html( $vname )
                    );
                }
                echo '</select>';

                if ( $multiple ) {
                    echo '<input type="hidden" name="wr_trendyol_attr[' . esc_attr( $attr_id ) . '][multiple]" value="1" />';
                }

                // Custom allowed ise:
                if ( $allow_custom ) {
                    echo '<br/><span class="description">Özel değer:</span><br/>';
                    echo '<input type="text" name="wr_trendyol_attr[' . esc_attr( $attr_id ) . '][custom]" 
                                  value="' . esc_attr( $stored_custom ) . '" style="max-width:260px;" />';
                }

            } else {

                // Değer listesi yoksa tek input
                echo '<input type="text" 
                           name="wr_trendyol_attr[' . esc_attr( $attr_id ) . '][custom]" 
                           value="' . esc_attr( $stored_custom ) . '" style="max-width:260px;" />';
            }

            echo '</p>';
        }
    }

    /**
     * Save selected Trendyol category meta.
     *
     * @param int $post_id Product ID.
     *
     * @return string Saved category ID.
     */
    public function save_category_meta( $post_id ) {
        $category_id = '';

        if ( isset( $_POST['wr_trendyol_category_id'] ) ) {
            $category_id = sanitize_text_field( wp_unslash( $_POST['wr_trendyol_category_id'] ) );

            update_post_meta(
                $post_id,
                '_wr_trendyol_category_id',
                $category_id
            );
            update_post_meta(
                $post_id,
                '_trendyol_category_id',
                $category_id
            );
        } elseif ( isset( $_POST['wr_trendyol_category'] ) ) { // Legacy fallback.
            $category_id = sanitize_text_field( wp_unslash( $_POST['wr_trendyol_category'] ) );

            update_post_meta( $post_id, '_wr_trendyol_category_id', $category_id );
            update_post_meta( $post_id, '_trendyol_category_id', $category_id );
        }

        return $category_id;
    }

    /**
     * Meta kaydet.
     *
     * @param int    $product_id Product ID.
     * @param object $post       WP_Post instance.
     */
    public function save_product_meta( $product_id, $post ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
        $category_id        = $this->save_category_meta( $product_id );
        $brand_id           = isset( $_POST['wr_trendyol_brand_id'] ) ? sanitize_text_field( wp_unslash( $_POST['wr_trendyol_brand_id'] ) ) : '';
        $barcode            = isset( $_POST['wr_trendyol_barcode'] ) ? sanitize_text_field( wp_unslash( $_POST['wr_trendyol_barcode'] ) ) : '';
        $dimensional_weight = isset( $_POST['wr_trendyol_dimensional_weight'] ) ? wc_format_decimal( wp_unslash( $_POST['wr_trendyol_dimensional_weight'] ) ) : '';
        $enabled            = ( isset( $_POST['wr_trendyol_enabled'] ) && 'yes' === $_POST['wr_trendyol_enabled'] ) ? 'yes' : 'no';

        update_post_meta( $product_id, '_wr_trendyol_brand_id', $brand_id );
        update_post_meta( $product_id, '_wr_trendyol_barcode', $barcode );
        update_post_meta( $product_id, '_wr_trendyol_dimensional_weight', $dimensional_weight );
        update_post_meta( $product_id, '_wr_trendyol_enabled', $enabled );

        if ( isset( $_POST['wr_trendyol_attr'] ) && is_array( $_POST['wr_trendyol_attr'] ) ) {
            foreach ( $_POST['wr_trendyol_attr'] as $attr_id => $data ) {
                $attr_id = absint( $attr_id );
                if ( ! $attr_id ) {
                    continue;
                }
                $is_multiple = ! empty( $data['multiple'] );

                $raw_value_ids = isset( $data['value_id'] ) ? $data['value_id'] : [];
                $value_ids     = [];

                if ( is_array( $raw_value_ids ) ) {
                    foreach ( $raw_value_ids as $raw_value_id ) {
                        $val = absint( $raw_value_id );
                        if ( $val ) {
                            $value_ids[] = $val;
                        }
                    }
                } else {
                    $single_id = absint( $raw_value_ids );
                    if ( $single_id ) {
                        $value_ids[] = $single_id;
                    }
                }

                $custom = isset( $data['custom'] ) ? sanitize_text_field( wp_unslash( $data['custom'] ) ) : '';

                $meta_key = '_wr_trendyol_attr_' . $attr_id;

                if ( empty( $value_ids ) && '' === $custom ) {
                    delete_post_meta( $product_id, $meta_key );
                } else {
                    update_post_meta(
                        $product_id,
                        $meta_key,
                        [
                            'value_id' => $is_multiple ? $value_ids : ( ( isset( $value_ids[0] ) ? $value_ids[0] : 0 ) ),
                            'custom'   => $custom,
                        ]
                    );
                }
            }
        }
    }

    /**
     * AJAX: kategoriye göre attribute alanlarını yeniden render et.
     */
    public function ajax_load_attributes() {
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( __( 'You are not allowed to load attributes.', 'wisdom-rain-trendyol-entegrasyon' ) );
        }

        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wr_trendyol_product_nonce' ) ) {
            wp_send_json_error( __( 'Security check failed.', 'wisdom-rain-trendyol-entegrasyon' ) );
        }

        $product_id  = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;

        if ( ! $category_id ) {
            wp_send_json_error( __( 'Category ID is missing.', 'wisdom-rain-trendyol-entegrasyon' ) );
        }

        $client     = $this->plugin->get_api_client();
        $attributes = $client->get_category_attributes( $category_id );

        if ( is_wp_error( $attributes ) ) {
            $code    = $attributes->get_error_code();
            $message = $attributes->get_error_message();

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf( 'WR TRENDYOL ATTR ERROR [%s] for category %d: %s', $code, $category_id, $message ) );
            }

            if ( 'wr_trendyol_attr_empty' === $code ) {
                wp_send_json_error(
                    [
                        'message'     => __( 'No attributes available for this Trendyol category.', 'wisdom-rain-trendyol-entegrasyon' ),
                        'category_id' => $category_id,
                        'error_code'  => $code,
                    ]
                );
            }

            wp_send_json_error(
                [
                    'message'     => __( 'Error while fetching attributes from Trendyol API.', 'wisdom-rain-trendyol-entegrasyon' ),
                    'category_id' => $category_id,
                    'error_code'  => $code,
                ]
            );
        }

        ob_start();
        $this->render_attributes_fields_static( $product_id, $category_id, $attributes );
        $html = ob_get_clean();

        wp_send_json_success(
            [
                'category_id' => $category_id,
                'count'       => count( $attributes ),
                'attributes'  => $attributes,
                'html'        => $html,
            ]
        );
    }

    /**
     * AJAX: ürünü Trendyol'a gönder.
     */
    public function ajax_push_product() {
        check_ajax_referer( 'wr_trendyol_product_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( [ 'message' => __( 'Yetkisiz.', 'wisdom-rain-trendyol-entegrasyon' ) ] );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        if ( ! $product_id ) {
            wp_send_json_error( [ 'message' => __( 'Geçersiz ürün ID.', 'wisdom-rain-trendyol-entegrasyon' ) ] );
        }

        require_once WR_TRENDYOL_PLUGIN_PATH . 'includes/class-wr-trendyol-product-mapper.php';
        require_once WR_TRENDYOL_PLUGIN_PATH . 'includes/class-wr-trendyol-product-sync.php';

        $client = $this->plugin->get_api_client();

        $mapper = new \WR_Trendyol_Product_Mapper( $client );
        $sync   = new \WR_Trendyol_Product_Sync( $client, $mapper );

        $result = $sync->push_single_product( $product_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error(
                [
                    'message' => $result->get_error_message(),
                ]
            );
        }

        wp_send_json_success(
            [
                'message'    => __( "Ürün Trendyol'a gönderildi.", 'wisdom-rain-trendyol-entegrasyon' ),
                'product_id' => isset( $result['product_id'] ) ? $result['product_id'] : '',
            ]
        );
    }
}
