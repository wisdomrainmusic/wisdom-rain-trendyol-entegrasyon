jQuery(function($){

    console.log("WR TRENDYOL PRODUCT JS ACTIVE");

    const pushBtn = $('#wr_trendyol_push_product_btn');

    if (pushBtn.length) {
        console.log("WR Trendyol: Push button found.");

        pushBtn.on('click', function(e){
            e.preventDefault();

            const product_id = $(this).data('product-id');
            const nonce      = wr_trendyol_product_data.nonce;

            if (!product_id) {
                alert("Geçersiz ürün ID.");
                return;
            }

            $(this).prop("disabled", true).text("Gönderiliyor...");

            $.ajax({
                url: wr_trendyol_product_data.ajaxurl,
                method: "POST",
                dataType: "json",
                data: {
                    action: "wr_trendyol_push_product",
                    product_id: product_id,
                    nonce: nonce
                },
                success: function(res){
                    console.log("WR PUSH RESPONSE:", res);

                    if (res.success) {
                        $('#wr_trendyol_push_result')
                            .text(res.data.message)
                            .css('color', 'green');
                    } else {
                        $('#wr_trendyol_push_result')
                            .text(res.data.message || "Hata oluştu.")
                            .css('color', 'red');
                    }
                },
                error: function(xhr){
                    console.error("WR PUSH AJAX ERROR:", xhr.responseText);
                    $('#wr_trendyol_push_result')
                        .text("Sunucu hatası.")
                        .css('color', 'red');
                },
                complete: function(){
                    pushBtn.prop("disabled", false).text("Ürünü Trendyol'a Gönder");
                }
            });
        });
    } else {
        console.warn("WR Trendyol: Push button NOT found.");
    }

});
