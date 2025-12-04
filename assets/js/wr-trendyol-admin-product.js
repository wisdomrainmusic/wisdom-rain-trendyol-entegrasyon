jQuery(function($){

    console.log("WR TRENDYOL PRODUCT JS ACTIVE");

    const btn = $('#wr_trendyol_load_attributes_btn');

    if (!btn.length) {
        console.warn("WR Trendyol: Load button not found.");
        return;
    }

    btn.on("click", function (e) {
        e.preventDefault();

        // 🔥 DOĞRU ALAN
        const category_id = $('#wr_trendyol_category_id').val();
        const nonce = wr_trendyol_product_data.nonce;
        const post_id = wr_trendyol_product_data.post_id;

        console.log("SELECTED CATEGORY ID:", category_id);

        // ❗ DOĞRU VALIDATION
        if (!category_id || category_id.trim() === "") {
            alert("Lütfen bir Trendyol kategorisi seçin.");
            return;
        }

        btn.prop("disabled", true).text("Yükleniyor...");

        $.ajax({
            url: ajaxurl,
            method: "POST",
            dataType: "json",
            data: {
                action: "wr_trendyol_load_attributes",
                category_id: category_id,
                post_id: post_id,
                nonce: nonce
            },

            success: function (response) {
                console.log("WR ATTR RESPONSE:", response);

                if (!response) {
                    alert("Sunucudan geçersiz yanıt alındı.");
                    return;
                }

                if (response.success === true) {

                    const payload = response.data || [];
                    const attributes = Array.isArray(payload)
                        ? payload
                        : (payload.attributes || []);

                    if (!attributes.length) {
                        alert("Bu kategori için Trendyol zorunlu özellik bulunmuyor.");
                        return;
                    }

                    // 🔥 FORM ALANINA YAZ
                    const box = $('#wr_trendyol_attributes_box');

                    if (box.length) {

                        box.html(""); // önce temizle

                        attributes.forEach(attr => {

                            const row = `
                                <div class="wr-trendyol-attr-row" style="margin-bottom:10px;">
                                    <label style="font-weight:bold;">${attr.name}</label>
                                    <input type="text"
                                           name="wr_trendyol_attributes[${attr.id}]"
                                           style="width:100%; padding:6px;"
                                           value="" />
                                </div>
                            `;

                            box.append(row);
                        });
                    }

                    alert("Özellikler başarıyla yüklendi.");
                    return;
                }

                // ❌ ERROR CASE
                const msg = (response.data && response.data.message)
                    ? response.data.message
                    : "Trendyol'dan geçersiz yanıt alındı.";

                alert(msg);
            },

            error: function (xhr, status) {
                console.error("WR AJAX ERROR:", status, xhr.responseText);
                alert("Trendyol bağlantı hatası. Lütfen tekrar deneyin.");
            },

            complete: function () {
                btn.prop("disabled", false).text("Özellikleri Yükle");
            }
        });
    });

});
