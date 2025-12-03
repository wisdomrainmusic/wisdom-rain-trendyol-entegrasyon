<?php

defined('ABSPATH') || exit;

class WRTI_Category_Manager {

    private static $option_key = 'wrti_category_tree';

    public static function fetch_categories() {

        $settings = get_option('wrti_settings');
        if (!$settings) {
            return new WP_Error("missing_settings", "API ayarları bulunamadı.");
        }

        $apiKey    = $settings['api_key'];
        $apiSecret = $settings['api_secret'];

        $url = "https://apigw.trendyol.com/integration/product/product-categories";

        $args = [
            'headers' => [
                "Authorization" => "Basic " . base64_encode($apiKey . ':' . $apiSecret),
                "User-Agent"    => "WRTI-CategoryFetcher/1.0",
                "Content-Type"  => "application/json"
            ],
            'timeout' => 45
        ];

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        if (empty($body)) {
            return new WP_Error("empty_response", "API boş veri döndürdü.");
        }

        update_option(self::$option_key, $body);

        return true;
    }

    public static function get_categories() {
        $json = get_option(self::$option_key);
        if (!$json) return [];
        return json_decode($json, true);

    }

}
