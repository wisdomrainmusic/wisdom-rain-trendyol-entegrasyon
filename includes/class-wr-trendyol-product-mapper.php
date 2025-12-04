<?php
use WR\Trendyol\WR_Trendyol_API_Client;
use WR\Trendyol\WR_Trendyol_Plugin;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WooCommerce product -> Trendyol product payload mapper
 */
class WR_Trendyol_Product_Mapper {

    /**
     * @var WR_Trendyol_API_Client
     */
    protected $client;

    public function __construct( WR_Trendyol_API_Client $client ) {
        $this->client = $client;
    }

    /**
     * Trendyol kargo firma ID eşlemesi.
     *
     * @return array<string,int>
     */
    protected function get_cargo_company_map() {
        return WR_Trendyol_Plugin::get_cargo_company_map();
    }

    /**
     * Değer (slug veya eski ID) bilgisini cargoCompanyId'ye çevirir.
     *
     * @param string|int $value Value from product meta or settings.
     *
     * @return int|null
     */
    protected function map_cargo_value_to_id( $value ) {
        return WR_Trendyol_Plugin::normalize_cargo_company_value( $value );
    }

    /**
     * Belirli bir ürün için cargoCompanyId seç.
     * - Önce ürün bazlı meta'ya bakar.
     * - Boşsa ayarlardaki varsayılanı kullanır.
     *
     * @param int $product_id
     * @return int  cargoCompanyId veya 0 (bulunamadı)
     */
    protected function get_cargo_company_id_for_product( $product_id ) {
        $product_id = absint( $product_id );

        // 1) Ürün bazlı seçim
        $product_cargo = get_post_meta( $product_id, '_wr_trendyol_cargo_company_id', true );
        $cargo_company_id = WR_Trendyol_Plugin::normalize_cargo_company_value( $product_cargo );
        if ( $cargo_company_id ) {
            return (int) $cargo_company_id;
        }

        // Variation ise parent değerini kontrol et
        $product = wc_get_product( $product_id );
        if ( $product && $product->is_type( 'variation' ) ) {
            $parent_id        = $product->get_parent_id();
            $parent_cargo_raw = $parent_id ? get_post_meta( $parent_id, '_wr_trendyol_cargo_company_id', true ) : '';
            $parent_cargo_id  = WR_Trendyol_Plugin::normalize_cargo_company_value( $parent_cargo_raw );

            if ( $parent_cargo_id ) {
                return (int) $parent_cargo_id;
            }
        }

        // 2) Plugin ayarlarından varsayılan
        $settings = $this->client->get_settings();
        $default_cargo = WR_Trendyol_Plugin::normalize_cargo_company_value( $settings['cargo_company_id'] ?? '' );
        if ( $default_cargo ) {
            return (int) $default_cargo;
        }

        return 0;
    }

    /**
     * Tek bir WooCommerce ürününü Trendyol payload'ına dönüştürür
     *
     * @param int $product_id
     *
     * @return array|WP_Error
     */
    public function map_single_product( $product_id ) {

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return new WP_Error( 'wr_trendyol_no_product', 'Ürün bulunamadı.' );
        }

        $category_id        = (int) get_post_meta( $product_id, '_trendyol_category_id', true );
        if ( ! $category_id ) {
            $category_id = (int) get_post_meta( $product_id, '_wr_trendyol_category_id', true );
        }
        $brand_id           = (int) get_post_meta( $product_id, '_wr_trendyol_brand_id', true );
        $barcode            = (string) get_post_meta( $product_id, '_wr_trendyol_barcode', true );
        $dimensional_weight = get_post_meta( $product_id, '_wr_trendyol_dimensional_weight', true );
        $enabled            = get_post_meta( $product_id, '_wr_trendyol_enabled', true ) === 'yes';

        if ( ! $enabled ) {
            return new WP_Error( 'wr_trendyol_not_enabled', 'Bu ürün Trendyol için işaretlenmemiş.' );
        }

        if ( ! $category_id ) {
            return new WP_Error( 'wr_trendyol_missing_category', 'Trendyol kategori ID boş olamaz.' );
        }

        if ( ! $brand_id ) {
            return new WP_Error( 'wr_trendyol_missing_brand', 'Trendyol brand ID boş olamaz.' );
        }

        if ( empty( $barcode ) ) {
            return new WP_Error( 'wr_trendyol_missing_barcode', 'Barkod zorunlu alandır. Lütfen ürün için barkod girin.' );
        }

        $settings = get_option( WR_Trendyol_Plugin::OPTION_KEY, [] );
        if ( ! is_array( $settings ) ) {
            $settings = [];
        }

        $settings = wp_parse_args( $settings, $this->client->get_settings() );

        $shipment_address_id = isset( $settings['shipment_address_id'] ) ? absint( $settings['shipment_address_id'] ) : 0;

        if ( ! $shipment_address_id ) {
            return new WP_Error( 'missing_shipment_address_id', 'Shipment Address ID ayarlanmamış.' );
        }

        $cargo_company_id = $this->get_cargo_company_id_for_product( $product_id );

        if ( ! $cargo_company_id ) {
            return new WP_Error(
                'wr_trendyol_missing_cargo_company',
                __( 'Trendyol için kargo firması (cargoCompanyId) seçilmemiş. Lütfen bir kargo firması seçin.', 'wisdom-rain-trendyol-entegrasyon' )
            );
        }

        if ( ! WR_Trendyol_Plugin::is_allowed_cargo_company_id( $cargo_company_id ) ) {
            error_log( sprintf( 'WR TRENDYOL PAYLOAD ABORT: product %d invalid cargoCompanyId=%s', $product_id, wp_json_encode( $cargo_company_id ) ) );

            return new WP_Error(
                'wr_trendyol_invalid_cargo_company',
                __( 'Geçersiz Trendyol kargo firması seçildi. Lütfen geçerli bir cargoCompanyId seçin.', 'wisdom-rain-trendyol-entegrasyon' )
            );
        }

        if ( $dimensional_weight === '' ) {
            $dimensional_weight = 1;
        }

        $sku         = $product->get_sku() ? $product->get_sku() : 'WR-' . $product_id;
        $title       = $product->get_name();
        $description = wp_strip_all_tags( $product->get_description() );
        if ( ! $description ) {
            $description = wp_strip_all_tags( $product->get_short_description() );
        }

        // Görseller
        $image_urls = array();
        $attachment_ids = $product->get_gallery_image_ids();
        $main_image_id  = $product->get_image_id();

        if ( $main_image_id ) {
            $url = wp_get_attachment_url( $main_image_id );
            if ( $url ) {
                $image_urls[] = $url;
            }
        }

        if ( ! empty( $attachment_ids ) ) {
            foreach ( $attachment_ids as $aid ) {
                $url = wp_get_attachment_url( $aid );
                if ( $url && ! in_array( $url, $image_urls, true ) ) {
                    $image_urls[] = $url;
                }
            }
        }

        $images = array();
        foreach ( $image_urls as $url ) {
            $images[] = array( 'url' => $url );
        }

        // Attribute payload
        $attributes_payload = $this->build_attributes_payload( $product_id, $category_id );

        if ( is_wp_error( $attributes_payload ) ) {
            return $attributes_payload;
        }

        $quantity = $product->get_stock_quantity();
        if ( $quantity === null ) {
            $quantity = 0;
        }

        $prices = $this->map_prices( $product );
        if ( is_wp_error( $prices ) ) {
            return $prices;
        }

        $logistics = $this->map_logistics( $product_id, $settings );
        if ( is_wp_error( $logistics ) ) {
            return $logistics;
        }

        $payload_item = array(
            'barcode'           => $barcode,
            'title'             => $title,
            'brandId'           => $brand_id,
            'categoryId'        => $category_id,
            'stockCode'         => $sku,
            'quantity'          => (int) $quantity,
            'dimensionalWeight' => (float) $dimensional_weight,
            'description'       => $description,
            'productMainId'     => $sku,
            'images'            => $images,
            'attributes'        => $attributes_payload,
            'listPrice'         => $prices['listPrice'],
            'salePrice'         => $prices['salePrice'],
            'vatRate'           => $prices['vatRate'],
            'cargoCompanyId'    => (int) $cargo_company_id,
            'deliveryDuration'  => $logistics['deliveryDuration'],
            'shipmentAddressId' => $shipment_address_id,
            'returnAddressId'   => $logistics['returnAddressId'],
        );

        error_log( sprintf(
            'WR TRENDYOL PAYLOAD LOGISTICS: product %d cargoCompanyId=%d deliveryDuration=%d shipmentAddressId=%d returnAddressId=%d',
            $product_id,
            $cargo_company_id,
            isset( $logistics['deliveryDuration'] ) ? (int) $logistics['deliveryDuration'] : 0,
            isset( $logistics['shipmentAddressId'] ) ? (int) $logistics['shipmentAddressId'] : 0,
            isset( $logistics['returnAddressId'] ) ? (int) $logistics['returnAddressId'] : 0
        ) );

        return $payload_item;
    }

    /**
     * Ürünün seçili Trendyol attribute meta'larını JSON formatına çevirir.
     *
     * @param int $product_id
     * @param int $category_id
     *
     * @return array|WP_Error
     */
    protected function build_attributes_payload( $product_id, $category_id ) {

        $attrs = $this->client->get_category_attributes( $category_id );
        if ( is_wp_error( $attrs ) ) {
            return $attrs;
        }

        $payload = array();

        foreach ( $attrs as $attr ) {
            $attr_id      = isset( $attr['attributeId'] ) ? (int) $attr['attributeId'] : ( isset( $attr['id'] ) ? (int) $attr['id'] : 0 );
            $required     = ! empty( $attr['required'] ) || ! empty( $attr['mandatory'] );
            $multiple     = ! empty( $attr['multipleValues'] );
            $allow_custom = ! empty( $attr['customValue'] ) || ! empty( $attr['allowCustom'] );

            if ( ! $attr_id ) {
                continue;
            }

            $meta_key = '_wr_trendyol_attr_' . $attr_id;
            $stored   = get_post_meta( $product_id, $meta_key, true );

            $stored_values = [];
            $stored_custom = '';

            if ( is_array( $stored ) ) {
                if ( isset( $stored['value_id'] ) ) {
                    $stored_values = (array) $stored['value_id'];
                }

                if ( isset( $stored['custom'] ) ) {
                    $stored_custom = $stored['custom'];
                }
            }

            $stored_values = array_values( array_filter( array_map( 'absint', $stored_values ) ) );
            $stored_custom = is_string( $stored_custom ) ? trim( $stored_custom ) : '';

            if ( empty( $stored_values ) && '' === $stored_custom ) {
                if ( $required ) {
                    return new WP_Error(
                        'wr_trendyol_missing_attribute',
                        sprintf( 'Zorunlu attribute eksik: %d', $attr_id )
                    );
                }
                continue;
            }

            // Çoklu seçimde her value_id için ayrı entry ekliyoruz.
            if ( $multiple && ! empty( $stored_values ) ) {
                foreach ( $stored_values as $value_id ) {
                    $payload[] = [
                        'attributeId'      => $attr_id,
                        'attributeValueId' => (int) $value_id,
                    ];
                }
            } elseif ( ! empty( $stored_values ) ) {
                $payload[] = [
                    'attributeId'      => $attr_id,
                    'attributeValueId' => (int) $stored_values[0],
                ];
            }

            if ( $allow_custom && '' !== $stored_custom ) {
                $payload[] = [
                    'attributeId'         => $attr_id,
                    'attributeValueId'    => 0,
                    'customAttributeValue' => $stored_custom,
                ];
            }
        }

        return $payload;
    }

    /**
     * Map WooCommerce prices to Trendyol payload.
     *
     * @param \WC_Product $product Product instance.
     *
     * @return array|WP_Error
     */
    protected function map_prices( $product ) {
        $regular_price = $product->get_regular_price();
        $sale_price    = $product->get_sale_price();

        if ( '' === $regular_price ) {
            return new WP_Error( 'wr_trendyol_missing_regular_price', __( 'Ürünün normal fiyatı (regular price) bulunamadı.', 'wisdom-rain-trendyol-entegrasyon' ) );
        }

        $regular_price = (float) wc_format_decimal( $regular_price );
        $sale_price    = '' !== $sale_price ? (float) wc_format_decimal( $sale_price ) : $regular_price;

        $vat_rate = $this->resolve_vat_rate( $product );
        if ( is_wp_error( $vat_rate ) ) {
            return $vat_rate;
        }

        return array(
            'listPrice' => $regular_price,
            'salePrice' => $sale_price,
            'vatRate'   => $vat_rate,
        );
    }

    /**
     * Extract VAT rate percentage from WooCommerce tax settings.
     *
     * @param \WC_Product $product Product instance.
     *
     * @return float|WP_Error
     */
    protected function resolve_vat_rate( $product ) {
        $tax_class = $product->get_tax_class();
        $rates     = WC_Tax::get_rates( $tax_class );

        if ( empty( $rates ) && '' !== $tax_class ) {
            $rates = WC_Tax::get_rates( '' );
        }

        if ( empty( $rates ) ) {
            return new WP_Error( 'wr_trendyol_missing_vat_rate', __( 'Vergi oranı bulunamadı. Lütfen ürün için geçerli bir vergi sınıfı seçin.', 'wisdom-rain-trendyol-entegrasyon' ) );
        }

        $rate = reset( $rates );
        $rate_value = isset( $rate['rate'] ) ? (float) $rate['rate'] : null;

        if ( null === $rate_value ) {
            return new WP_Error( 'wr_trendyol_invalid_vat_rate', __( 'Vergi oranı çözümlenemedi.', 'wisdom-rain-trendyol-entegrasyon' ) );
        }

        return $rate_value;
    }

    /**
     * Map logistics fields required by Trendyol.
     *
     * @return array|WP_Error
     */
    protected function map_logistics( $product_id = 0, array $settings = [] ) {
        if ( empty( $settings ) ) {
            $settings = $this->client->get_settings();
        }

        $cargo_company_id = $product_id ? $this->get_cargo_company_id_for_product( $product_id ) : $this->map_cargo_value_to_id( $settings['cargo_company_id'] ?? '' );
        $delivery_duration = isset( $settings['delivery_duration'] ) ? absint( $settings['delivery_duration'] ) : 1;
        $shipment_address_id = isset( $settings['shipment_address_id'] ) ? absint( $settings['shipment_address_id'] ) : 0;
        $return_address_id   = isset( $settings['return_address_id'] ) ? absint( $settings['return_address_id'] ) : 0;

        $delivery_duration = max( 1, min( 7, $delivery_duration ) );

        if ( ! $cargo_company_id ) {
            return new WP_Error( 'wr_trendyol_missing_cargo_company', __( 'Lütfen bir kargo firması seçin.', 'wisdom-rain-trendyol-entegrasyon' ) );
        }

        if ( ! WR_Trendyol_Plugin::is_allowed_cargo_company_id( $cargo_company_id ) ) {
            error_log( sprintf( 'WR TRENDYOL LOGISTICS INVALID CARGO: product %d cargoCompanyId=%s', absint( $product_id ), wp_json_encode( $cargo_company_id ) ) );

            return new WP_Error( 'wr_trendyol_invalid_cargo_company', __( 'Geçersiz Trendyol kargo firması seçildi. Lütfen geçerli bir cargoCompanyId seçin.', 'wisdom-rain-trendyol-entegrasyon' ) );
        }

        if ( ! $shipment_address_id ) {
            return new WP_Error( 'missing_shipment_address_id', 'Shipment Address ID ayarlanmamış.' );
        }

        if ( ! $return_address_id ) {
            return new WP_Error( 'wr_trendyol_missing_return_address', __( 'Trendyol returnAddressId boş olamaz.', 'wisdom-rain-trendyol-entegrasyon' ) );
        }

        return array(
            'cargoCompanyId'    => $cargo_company_id,
            'deliveryDuration'  => $delivery_duration,
            'shipmentAddressId' => $shipment_address_id,
            'returnAddressId'   => $return_address_id,
        );
    }
}
