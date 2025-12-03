<?php
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
        );

        // Fiyat bilgisini burada göndermiyoruz; ayrı price-and-inventory endpoint'i ile yapılacak.
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
                    'attributeId' => $attr_id,
                    'customValue' => $stored_custom,
                ];
            }
        }

        return $payload;
    }
}
