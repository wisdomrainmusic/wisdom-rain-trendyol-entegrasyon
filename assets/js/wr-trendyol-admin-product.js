jQuery(function($){

    console.log("WR TRENDYOL PRODUCT JS ACTIVE");

    const dropdown = $("#wr_trendyol_category_select");
    const hidden   = $("#wr_trendyol_category_id");

    if (dropdown.length) {
        dropdown.on("change", function () {
            const selectedID = $(this).find(":selected").data("category-id") || "";
            hidden.val(selectedID);

            console.log("CATEGORY SELECTED:", selectedID);
        });
    }

    // Evrensel buton yakalama
    $(document).on('click', '#wr-load-attributes, #wr_load_attributes, .wr-load-attributes, .wr-load-attr', function(e){
        e.preventDefault();

        console.log("ATTR BUTTON CLICKED");

        let categoryId = $('#wr_trendyol_category_id').val() || $('#wr_trendyol_category_select').val() || $('#wr_trendyol_category').val();
        let productId  = $('#post_ID').val();

        if(!categoryId){
            alert("Lütfen Trendyol kategorisi seçin");
            return;
        }

        $.ajax({
            url: wrTrendyol.ajax_url,
            type: "POST",
            data: {
                action: "wr_load_attributes",
                nonce: wrTrendyol.nonce,
                category_id: categoryId,
                product_id: productId
            },
            beforeSend: function(){
                console.log("AJAX SENDING...");
            },
            success: function(res){
                console.log("AJAX RESPONSE:", res);

                if(res.success){
                    alert("Özellikler başarıyla yüklendi!");
                    location.reload();
                } else {
                    alert("Hata: " + res.data.message);
                }
            },
            error: function(err){
                console.error("AJAX ERROR:", err);
            }
        });

    });

});
