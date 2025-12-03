jQuery(document).ready(function ($) {

    console.log("WR TRENDYOL ADMIN JS LOADED");

    $("#wr_trendyol_category_id, #wr_trendyol_category").on("change", function () {

        let catId = $(this).val();
        if (!catId) return;

        console.log("Selected category:", catId);

        $.ajax({
            url: wrTrendyol.ajax_url,
            method: "POST",
            data: {
                action: "wr_load_attributes",
                nonce: wrTrendyol.nonce,
                categoryId: catId
            },
            success: function (res) {

                console.log("AJAX RESPONSE:", res);

                if (!res.success) {
                    alert("Failed: " + res.data.message);
                    return;
                }

                // Build UI
                let box = $("#wr_trendyol_attributes_box");
                box.html("");

                res.data.categoryAttributes.forEach(function (attr) {

                    let html = `<div class='wr-attr-field'>
                        <label><strong>${attr.attributeName}</strong></label>`;

                    if (attr.attributeValues.length === 0 || attr.customValue) {
                        html += `<input type='text' name='wr_attr_${attr.attributeId}' />`;
                    } else {

                        let multiple = attr.multipleValues ? "multiple" : "";
                        let name = attr.multipleValues ? `wr_attr_${attr.attributeId}[]` : `wr_attr_${attr.attributeId}`;

                        html += `<select name='${name}' ${multiple}>`;

                        attr.attributeValues.forEach(function (val) {
                            html += `<option value='${val.id}'>${val.name}</option>`;
                        });

                        html += `</select>`;
                    }

                    html += `</div>`;
                    box.append(html);
                });
            }
        });
    });
});

jQuery(function($){

    console.log("WR TRENDYOL PRODUCT JS ACTIVE");

    // Kategori seçildiğinde hidden meta alanına kaydet
    $('#wr_trendyol_category_id, #wr_trendyol_category').on('change', function () {
        let val = $(this).val();
        console.log("CATEGORY SELECTED:", val);
        $('#_wr_trendyol_category_id').val(val);
    });

    // Özellikleri Yükle butonu
    $('#wr-load-attributes').on('click', function(e){
        e.preventDefault();

        let catId = $('#wr_trendyol_category').val() || $('#_wr_trendyol_category_id').val() || $('#wr_trendyol_category_id').val();
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
