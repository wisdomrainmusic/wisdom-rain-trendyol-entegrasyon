<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** @var array  $settings */
/** @var string $test_url */

$selected_category = get_option( 'wr_trendyol_category_id', '' );
$category_options  = function_exists( 'wr_trendyol_get_category_options' ) ? wr_trendyol_get_category_options() : [];
$cargo_labels      = \WR\Trendyol\WR_Trendyol_Plugin::get_cargo_company_labels();

?>
<div class="wrap">
    <h1>Wisdom Rain – Trendyol Entegrasyon</h1>

    <p>Trendyol API bilgilerini girerek WooCommerce mağazanızı Trendyol ile entegre edin.</p>

    <hr />

    <p>
        <a href="<?php echo esc_url( $test_url ); ?>" class="button button-secondary">
            Trendyol Bağlantısını Test Et
        </a>
    </p>

    <form method="post" action="options.php" style="max-width: 720px;">

        <?php settings_fields( 'wr_trendyol_settings_group' ); ?>

        <table class="form-table" role="presentation">
            <tbody>

            <tr>
                <th scope="row">
                    <label for="wr_trendyol_seller_id">Seller / Supplier ID</label>
                </th>
                <td>
                    <input name="<?php echo esc_attr( \WR\Trendyol\WR_Trendyol_Plugin::OPTION_KEY ); ?>[seller_id]"
                           type="text"
                           id="wr_trendyol_seller_id"
                           value="<?php echo esc_attr( $settings['seller_id'] ); ?>"
                           class="regular-text" />
                    <p class="description">
                        Trendyol Satıcı Paneli &rarr; Hesap Bilgilerim &rarr; Entegrasyon Bilgileri ekranındaki Satıcı ID değerini girin.
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wr_trendyol_api_key">API Key (Kullanıcı Adı)</label>
                </th>
                <td>
                    <input name="<?php echo esc_attr( \WR\Trendyol\WR_Trendyol_Plugin::OPTION_KEY ); ?>[api_key]"
                           type="text"
                           id="wr_trendyol_api_key"
                           value="<?php echo esc_attr( $settings['api_key'] ); ?>"
                           class="regular-text" />
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wr_trendyol_api_secret">API Secret (Şifre)</label>
                </th>
                <td>
                    <input name="<?php echo esc_attr( \WR\Trendyol\WR_Trendyol_Plugin::OPTION_KEY ); ?>[api_secret]"
                           type="password"
                           id="wr_trendyol_api_secret"
                           value="<?php echo esc_attr( $settings['api_secret'] ); ?>"
                           class="regular-text" />
                </td>
            </tr>

            <tr>
                <th scope="row">Environment</th>
                <td>
                    <fieldset>
                        <label>
                            <input type="radio"
                                   name="<?php echo esc_attr( \WR\Trendyol\WR_Trendyol_Plugin::OPTION_KEY ); ?>[environment]"
                                   value="<?php echo esc_attr( \WR\Trendyol\WR_Trendyol_Plugin::ENV_PROD ); ?>"
                                <?php checked( $settings['environment'], \WR\Trendyol\WR_Trendyol_Plugin::ENV_PROD ); ?> />
                            Production
                        </label><br/>

                        <label>
                            <input type="radio"
                                   name="<?php echo esc_attr( \WR\Trendyol\WR_Trendyol_Plugin::OPTION_KEY ); ?>[environment]"
                                   value="<?php echo esc_attr( \WR\Trendyol\WR_Trendyol_Plugin::ENV_SANDBOX ); ?>"
                                <?php checked( $settings['environment'], \WR\Trendyol\WR_Trendyol_Plugin::ENV_SANDBOX ); ?> />
                            Sandbox / Test (dokümana göre URL ayrıca ayarlanmalıdır)
                        </label>
                    </fieldset>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wr_trendyol_category_id"><?php esc_html_e( 'Trendyol Category', 'wisdom-rain-trendyol-entegrasyon' ); ?></label>
                </th>
                <td>
                    <select name="wr_trendyol_category_id" id="wr_trendyol_category_id" style="min-width: 320px;">
                        <option value=""><?php esc_html_e( '— Select Trendyol Category —', 'wisdom-rain-trendyol-entegrasyon' ); ?></option>
                        <?php foreach ( $category_options as $cat_id => $label ) : ?>
                            <option value="<?php echo esc_attr( $cat_id ); ?>" <?php selected( (string) $selected_category, (string) $cat_id ); ?>><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e( 'Select the Trendyol category using the hierarchical list.', 'wisdom-rain-trendyol-entegrasyon' ); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wr_trendyol_user_agent">User-Agent</label>
                </th>
                <td>
                    <input name="<?php echo esc_attr( \WR\Trendyol\WR_Trendyol_Plugin::OPTION_KEY ); ?>[user_agent]"
                           type="text"
                           id="wr_trendyol_user_agent"
                           value="<?php echo esc_attr( $settings['user_agent'] ); ?>"
                           class="regular-text" />
                    <p class="description">
                        Trendyol dökümanına göre zorunlu. Örn: <code><?php echo esc_html( $settings['seller_id'] ?: '123456' ); ?> - Self Integration</code>.
                        Boş bırakırsanız otomatik oluşturulur.
                    </p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    Debug Modu
                </th>
                <td>
                    <label>
                        <input name="<?php echo esc_attr( \WR\Trendyol\WR_Trendyol_Plugin::OPTION_KEY ); ?>[debug]"
                               type="checkbox"
                               value="1"
                            <?php checked( ! empty( $settings['debug'] ) ); ?> />
                        Hata durumunda detaylı WP_Error verisi üret (geliştirme ortamında kullanın).
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wr_trendyol_cargo_company_id"><?php esc_html_e( 'Varsayılan Kargo Firması', 'wisdom-rain-trendyol-entegrasyon' ); ?></label>
                </th>
                <td>
                    <select name="<?php echo esc_attr( \WR\Trendyol\WR_Trendyol_Plugin::OPTION_KEY ); ?>[cargo_company_id]" id="wr_trendyol_cargo_company_id" style="min-width: 220px;">
                        <option value=""><?php esc_html_e( '— Seçiniz —', 'wisdom-rain-trendyol-entegrasyon' ); ?></option>
                        <?php foreach ( $cargo_labels as $cid => $label ) : ?>
                            <option value="<?php echo esc_attr( $cid ); ?>" <?php selected( (int) $settings['cargo_company_id'], (int) $cid ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description"><?php esc_html_e( 'Trendyol cargoCompanyId whitelist’ine göre resmi kargo firması ID’sini seçin.', 'wisdom-rain-trendyol-entegrasyon' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wr_trendyol_delivery_duration"><?php esc_html_e( 'Teslimat Süresi (gün)', 'wisdom-rain-trendyol-entegrasyon' ); ?></label>
                </th>
                <td>
                    <input type="number"
                           min="1"
                           max="7"
                           id="wr_trendyol_delivery_duration"
                           name="<?php echo esc_attr( \WR\Trendyol\WR_Trendyol_Plugin::OPTION_KEY ); ?>[delivery_duration]"
                           value="<?php echo esc_attr( $settings['delivery_duration'] ); ?>" />
                    <p class="description"><?php esc_html_e( 'Trendyol SLA gereği 1-7 gün arasında olmalıdır.', 'wisdom-rain-trendyol-entegrasyon' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wr_trendyol_shipment_address_id"><?php esc_html_e( 'Shipment Address ID', 'wisdom-rain-trendyol-entegrasyon' ); ?></label>
                </th>
                <td>
                    <input type="number"
                           id="wr_trendyol_shipment_address_id"
                           name="<?php echo esc_attr( \WR\Trendyol\WR_Trendyol_Plugin::OPTION_KEY ); ?>[shipment_address_id]"
                           value="<?php echo esc_attr( $settings['shipment_address_id'] ); ?>" />
                    <p class="description"><?php esc_html_e( 'Trendyol panelinde tanımlı shipmentAddressId değerini girin.', 'wisdom-rain-trendyol-entegrasyon' ); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row">
                    <label for="wr_trendyol_return_address_id"><?php esc_html_e( 'Return Address ID', 'wisdom-rain-trendyol-entegrasyon' ); ?></label>
                </th>
                <td>
                    <input type="number"
                           id="wr_trendyol_return_address_id"
                           name="<?php echo esc_attr( \WR\Trendyol\WR_Trendyol_Plugin::OPTION_KEY ); ?>[return_address_id]"
                           value="<?php echo esc_attr( $settings['return_address_id'] ); ?>" />
                    <p class="description"><?php esc_html_e( 'Trendyol panelinde tanımlı returnAddressId değerini girin.', 'wisdom-rain-trendyol-entegrasyon' ); ?></p>
                </td>
            </tr>

            </tbody>
        </table>

        <?php submit_button(); ?>

    </form>
</div>
