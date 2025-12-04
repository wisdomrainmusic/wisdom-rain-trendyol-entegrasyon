jQuery(function ($) {

    console.log('WR TRENDYOL PRODUCT JS ACTIVE');

    const config = window.wrTrendyolProduct || window.WRTrendyolProduct || null;

    // Keep hidden/category select in sync for API calls
    const $dropdown = $('#wr_trendyol_category_select');
    const $hidden = $('#wr_trendyol_category_id');

    if ($dropdown.length) {
        $dropdown.on('change', function () {
            const selectedID = $(this).find(':selected').data('category-id') || '';
            $hidden.val(selectedID);
            console.log('CATEGORY SELECTED:', selectedID);
        });
    }

    const $categoryField = $('#wr_trendyol_category_id');
    const $loadBtn = $('#wr_trendyol_load_attributes_btn');

    function loadCategoryAttributes(triggerSource) {
        const productId = $loadBtn.data('product-id') || $('#post_ID').val();
        const categoryId = $categoryField.val();

        if (!config) {
            console.error('WR TRENDYOL CONFIG MISSING');
            alert('Script yapılandırması bulunamadı. Lütfen sayfayı yenileyip tekrar deneyin.');
            return;
        }

        if (!categoryId) {
            alert(config.missing_category_msg || 'Lütfen önce Trendyol kategorisini seçin.');
            return;
        }

        $loadBtn.prop('disabled', true).text('Yükleniyor...');

        $.post(
            config.ajax_url,
            {
                action: 'wr_trendyol_load_attributes',
                nonce: config.nonce,
                product_id: productId,
                category_id: categoryId,
            }
        )
            .done(function (response) {

                if (!response || !response.success || !response.data) {
                    console.error('WR TRENDYOL ATTR ERROR', response);
                    alert((response && response.data) ? response.data : 'Özellikler yüklenirken bir hata oluştu.');
                    return;
                }

                if (response.data.html) {
                    $('#wr_trendyol_attributes_wrap').html(response.data.html);
                }

                console.log('WR TRENDYOL ATTRIBUTES LOADED', {
                    category_id: response.data.category_id,
                    count: response.data.count,
                    attributes: response.data.attributes,
                    trigger: triggerSource,
                });

            })
            .fail(function (xhr) {
                console.error('WR TRENDYOL ATTR AJAX FAIL', xhr);
                alert('Sunucuya ulaşılamadı. Lütfen sayfayı yenileyip tekrar deneyin.');
            })
            .always(function () {
                $loadBtn.prop('disabled', false).text('Özellikleri Yükle');
            });
    }

    // Auto-load attributes when the category is changed manually
    $categoryField.on('change', function () {
        loadCategoryAttributes('category-change');
    });

    $loadBtn.on('click', function (e) {
        e.preventDefault();
        loadCategoryAttributes('button');
    });
});
