<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Trendyol ürün senkronizasyon işlemleri
 */
class WR_Trendyol_Product_Sync {

    /**
     * @var WR_Trendyol_API_Client
     */
    protected $client;

    /**
     * @var WR_Trendyol_Product_Mapper
     */
    protected $mapper;

    public function __construct( WR_Trendyol_API_Client $client, WR_Trendyol_Product_Mapper $mapper ) {
        $this->client  = $client;
        $this->mapper  = $mapper;
    }

    /**
     * Tek bir ürünü Trendyol'a gönderir
     *
     * @param int $product_id
     *
     * @return array|WP_Error
     */
    public function push_single_product( $product_id ) {

        $payload_item = $this->mapper->map_single_product( $product_id );
        if ( is_wp_error( $payload_item ) ) {
            return $payload_item;
        }

        $payload = array(
            'items' => array( $payload_item ),
        );

        $seller_id = get_option( WR_TRENDYOL_OPTION_KEY );
        // seller id zaten client içinde, path'te kullanılacak

        $result = $this->client->request(
            'POST',
            sprintf( '/sapigw/suppliers/%s/products', $this->client_seller_id() ),
            array(
                'body' => $payload,
            )
        );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $body = isset( $result['body'] ) ? $result['body'] : array();

        // Response formatını dokümana göre uyarlamak gerekebilir. Basit senaryo:
        $product_tid = '';
        if ( isset( $body['items'][0]['id'] ) ) {
            $product_tid = $body['items'][0]['id'];
        } elseif ( isset( $body['items'][0]['productId'] ) ) {
            $product_tid = $body['items'][0]['productId'];
        }

        if ( $product_tid ) {
            update_post_meta( $product_id, '_wr_trendyol_product_id', $product_tid );
        }

        return array(
            'product_id'   => $product_tid,
            'raw_response' => $body,
        );
    }

    /**
     * Client'tan seller id'yi almak için küçük helper
     */
    protected function client_seller_id() {
        $ref = new ReflectionClass( $this->client );
        if ( $ref->hasProperty( 'seller_id' ) ) {
            $prop = $ref->getProperty( 'seller_id' );
            $prop->setAccessible( true );
            return $prop->getValue( $this->client );
        }

        return '';
    }
}
