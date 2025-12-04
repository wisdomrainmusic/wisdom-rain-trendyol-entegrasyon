jQuery(function($){

    console.log("WR TRENDYOL PRODUCT JS ACTIVE");

    // Kategori seçildiğinde hidden meta alanına kaydet
    $('#wr_trendyol_category_id, #wr_trendyol_category, #wr_trendyol_category_select').on('change', function () {
        const val = $(this).find(':selected').data('category-id') || $(this).val();
        console.log("CATEGORY SELECTED:", val);
        $('#wr_trendyol_category_id').val(val);
        $('#_wr_trendyol_category_id').val(val);
    });

    // Özellikleri Yükle butonu
    $('#wr-load-attributes').on('click', function(e){
        e.preventDefault();

        let catId = $('#wr_trendyol_category_id').val() || $('#wr_trendyol_category_select').val() || $('#wr_trendyol_category').val() || $('#_wr_trendyol_category_id').val();
        let postId = $('#post_ID').val();

        const productNonce = (typeof WRTrendyolProduct !== 'undefined' && WRTrendyolProduct.nonce)
            ? WRTrendyolProduct.nonce
            : (typeof wrTrendyol !== 'undefined' ? wrTrendyol.nonce : '');

        console.log("LOAD ATTRIBUTES CLICK — cat:", catId, "post:", postId);

        if(!catId){
            alert("Kategori seçilmedi.");
            return;
        }

        $.post(ajaxurl, {
            action: 'wr_trendyol_load_attributes',
            category_id: catId,
            post_id: postId,
            nonce: productNonce
        }, function(response){
            console.log("ATTRIBUTE AJAX RESPONSE:", response);
            $('#wr_trendyol_attributes').html(response.html);
        });
    });
});
