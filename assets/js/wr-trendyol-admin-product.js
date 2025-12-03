jQuery(function($) {

    console.log("WR TRENDYOL ADMIN JS ACTIVE");

    const catSelect = $('#wr_trendyol_category_id');
    const loadBtn   = $('#wr_trendyol_load_attributes_btn');
    const wrapper   = $('#wr_trendyol_attributes_wrap');

    /**
     * Load attributes via AJAX
     */
    function loadAttributes() {

        const category_id = catSelect.val();
        const product_id  = $('#post_ID').val();

        if (!category_id) {
            wrapper.html('<p>Kategori seçilmedi.</p>');
            return;
        }

        wrapper.html('<p>Yükleniyor...</p>');

        $.ajax({
            url: WRTrendyolProduct.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wr_trendyol_load_attributes',
                nonce: WRTrendyolProduct.nonce,
                product_id: product_id,
                category_id: category_id
            },
            success: function(response) {
                if (response.success) {
                    wrapper.html(response.data.html);
                } else {
                    wrapper.html('<p>Attribute yüklenirken hata oluştu.</p>');
                }
            },
            error: function(xhr) {
                console.log("WR TRENDYOL AJAX ERROR:", xhr.responseText);
                wrapper.html('<p>Sunucu hatası.</p>');
            }
        });
    }

    /**
     * Select2 support — change çalışmadığı için select2:select event’i kullanıyoruz
     */
    catSelect.on('select2:select', function () {
        loadAttributes();
    });

    /**
     * Manual load button → same function
     */
    loadBtn.on('click', function () {
        loadAttributes();
    });

});
