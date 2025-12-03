<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WR_Trendyol_Categories {

    /**
     * Read Trendyol categories JSON and return associative array.
     *
     * @return array
     */
    public static function get_categories_raw() {
        $file = WR_TRENDYOL_PLUGIN_DIR . 'assets/data/trendyol-categories.json';

        if ( ! file_exists( $file ) ) {
            return array();
        }

        $json = file_get_contents( $file );
        if ( ! $json ) {
            return array();
        }

        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            return array();
        }

        return $data;
    }

    /**
     * Return flat id => "Parent > Child" list for single dropdown usage.
     *
     * @return array
     */
    public static function get_flat_options() {
        $raw = self::get_categories_raw();

        $options = array();

        $items = isset( $raw['items'] ) && is_array( $raw['items'] ) ? $raw['items'] : $raw;

        foreach ( $items as $item ) {
            self::walk_category( $item, array(), $options );
        }

        asort( $options, SORT_NATURAL | SORT_FLAG_CASE );

        return $options;
    }

    /**
     * Recursive walker to build path labels.
     *
     * @param array $node
     * @param array $parents
     * @param array $options
     */
    protected static function walk_category( $node, $parents, &$options ) {
        if ( empty( $node['id'] ) || empty( $node['name'] ) ) {
            return;
        }

        $current_path = array_merge( $parents, array( $node['name'] ) );
        $label        = implode( ' > ', $current_path );
        $id           = (string) $node['id'];

        $options[ $id ] = $label;

        if ( ! empty( $node['subCategories'] ) && is_array( $node['subCategories'] ) ) {
            foreach ( $node['subCategories'] as $child ) {
                self::walk_category( $child, $current_path, $options );
            }
        }
    }
}

if ( ! function_exists( 'wr_trendyol_get_category_options' ) ) {
    /**
     * Helper: get flat Trendyol category options.
     *
     * @return array
     */
    function wr_trendyol_get_category_options() {
        return WR_Trendyol_Categories::get_flat_options();
    }
}
