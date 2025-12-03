<?php

defined('ABSPATH') || exit;

class WRTI_Admin_Category_Page {

    public function __construct() {
        add_action('admin_menu', [ $this, 'menu' ]);
    }

    public function menu() {
        add_submenu_page(
            'wrti-main',
            'Kategori Yönetimi',
            'Kategori Yönetimi',
            'manage_options',
            'wrti-category-manager',
            [ $this, 'render_page' ]
        );
    }

    public function render_page() {

        if (isset($_POST['wrti_update_categories'])) {
            $result = WRTI_Category_Manager::fetch_categories();
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>HATA: '.$result->get_error_message().'</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>Kategori listesi başarıyla güncellendi.</p></div>';
            }
        }

        echo '<div class="wrap">';
        echo '<h1>Trendyol Kategori Yönetimi</h1>';
        echo '<form method="post">';
        echo '<button type="submit" name="wrti_update_categories" class="button button-primary">Kategori Ağacını Güncelle</button>';
        echo '</form>';

        echo '<hr>';

        $cats = WRTI_Category_Manager::get_categories();
        echo '<h2>Son Güncel Kategori Ağacı</h2>';
        echo '<pre style="background:#fff; padding:20px; border:1px solid #ccc; max-height:600px; overflow:auto;">';
        echo esc_html(json_encode($cats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo '</pre>';

        echo '</div>';
    }

}
