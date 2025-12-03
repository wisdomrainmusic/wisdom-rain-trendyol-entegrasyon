jQuery(function($) {

    // Kategori değiştiğinde attribute'ları yeniden yükle
    $('#wr_trendyol_category_id').on('change', function() {
        var categoryId = $(this).val();
        var productId  = $('#post_ID').val();

        if (!categoryId) {
            $('#wr_trendyol_attributes_wrap').html('<p>Kategori seçilmedi.</p>');
            return;
        }

        $('#wr_trendyol_attributes_wrap').html('<p>Yükleniyor…</p>');

        $.post(
            WRTrendyolProduct.ajax_url,
            {
                action: 'wr_trendyol_load_attributes',
                nonce: WRTrendyolProduct.nonce,
                category_id: categoryId,
                product_id: productId
            },
            function(response) {
                if (response && response.success && response.data && response.data.html) {
                    $('#wr_trendyol_attributes_wrap').html(response.data.html);
                } else {
                    $('#wr_trendyol_attributes_wrap').html('<p>Attribute yüklenemedi.</p>');
                }
            }
        );
    });

    // Ürünü Trendyol'a gönder
    $('#wr_trendyol_push_product_btn').on('click', function(e) {
        e.preventDefault();

        var productId = $(this).data('product-id');
        var $result   = $('#wr_trendyol_push_result');

        if (!productId) {
            return;
        }

        $result.text('Gönderiliyor…');

        $.post(
            WRTrendyolProduct.ajax_url,
            {
                action: 'wr_trendyol_push_product',
                nonce: WRTrendyolProduct.nonce,
                product_id: productId
            },
            function(response) {
                if (response && response.success) {
                    $result.text(WRTrendyolProduct.push_success_msg);
                } else {
                    var msg = WRTrendyolProduct.push_error_msg;
                    if (response && response.data && response.data.message) {
                        msg = response.data.message;
                    }
                    $result.text(msg);
                }
            }
        );
    });
});
