jQuery(function($){

    console.log("WR TRENDYOL PRODUCT JS ACTIVE");

    $('#wr_trendyol_category_id').on('change', function(){

        let categoryId = $(this).val();
        let postId     = $('#post_ID').val();

        if(!categoryId){
            return;
        }

        console.log("TR CATEGORY CHANGED →", categoryId);

        // AJAX çağır
        $.post(ajaxurl, {
            action: 'wr_trendyol_fetch_attributes',
            post_id: postId,
            category_id: categoryId,
            _wpnonce: (typeof wrTrendyol !== 'undefined' ? wrTrendyol.nonce : '')
        }, function(response){

            console.log("ATTRIBUTE LOADER RESPONSE →", response);

            if(response.success){
                location.reload(); // alanları yenile
            } else {
                alert("Attribute yüklenemedi.");
            }
        });

    });

    const dropdown = $("#wr_trendyol_category_select");
    const hidden   = $("#wr_trendyol_category_id");

    if (dropdown.length) {
        dropdown.on("change", function () {
            const selectedID = $(this).find(":selected").data("category-id") || "";
            hidden.val(selectedID);

            console.log("CATEGORY SELECTED:", selectedID);
        });
    }

    $(document).on('click', '#wr_trendyol_load_attributes_btn', function(e){
        e.preventDefault();

        let catId =
            $('#wr_trendyol_category_id').val() ||
            $('#wr_trendyol_category_select').val() ||
            $('#wr_trendyol_category').val() ||
            $('#_wr_trendyol_category_id').val();

        let postId = $('#post_ID').val();

        const productNonce = (typeof WRTrendyolProduct !== 'undefined' && WRTrendyolProduct.nonce)
            ? WRTrendyolProduct.nonce
            : (typeof wrTrendyol !== 'undefined' ? wrTrendyol.nonce : '');

        console.log('LOAD ATTRIBUTES CLICK — cat:', catId, 'post:', postId);

        if (!catId) {
            alert('Kategori seçilmedi.');
            return;
        }

        $.post(ajaxurl, {
            action: 'wr_trendyol_load_attributes',
            category_id: catId,
            post_id: postId,
            nonce: productNonce
        }, function (response) {
            console.log("ATTRIBUTE AJAX RESPONSE", response);

            if (response.error) {
                alert(response.error);
                return;
            }

            if (response.success && response.html) {
                $('#wr_trendyol_attributes').html(response.html);
            } else {
                alert('Hata: ' + (response.data?.message || 'Bilinmeyen hata'));
            }
        });
    });

});
