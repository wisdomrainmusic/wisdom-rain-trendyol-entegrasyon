jQuery(function($){

    console.log("WR TRENDYOL PRODUCT JS ACTIVE");

    // Kategori seçildiğinde hidden meta alanına kaydet
    $('#wr_trendyol_category_id, #wr_trendyol_category, #wr_trendyol_category_select').on('change', function () {
        const val = $(this).find(':selected').data('category-id') || $(this).val();
        console.log("CATEGORY SELECTED:", val);
        $('#wr_trendyol_category_id').val(val);
        $('#_wr_trendyol_category_id').val(val);
    });
    // Legacy attribute loader handler intentionally removed. Canonical loader lives in wr-trendyol-admin-product.js.
});
