jQuery(function($){

    console.log("WR TRENDYOL PRODUCT JS ACTIVE");

    const btn = $('#wr_trendyol_load_attributes_btn');

    if (!btn.length) {
        console.warn("WR Trendyol: load button not found.");
        return;
    }

    btn.on("click", function (e) {
        e.preventDefault();

        const category_id = $('#product_cat').val() || null;
        const nonce = wr_trendyol_product_data.nonce;
        const post_id = wr_trendyol_product_data.post_id;

        if (!category_id) {
            alert("Lütfen bir kategori seçin.");
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

                // ❌ eski sistem burada alert(response) diyordu → [object Object]
                // ✔ yeni sistem: response.success kontrolü + güvenli JSON parse

                if (!response) {
                    alert("Sunucudan geçersiz yanıt alındı.");
                    return;
                }

                if (response.success === true) {

                    const payload = response.data || [];
                    const attributes = Array.isArray(payload) ? payload : (payload.attributes || []);

                    if (!attributes.length) {
                        alert("Bu kategori için Trendyol tarafından zorunlu özellik bulunmuyor.");
                        return;
                    }

                    // FORM ALANINA YAZ
                    const box = $('#wr_trendyol_attributes_box');
                    if (box.length) {
                        box.html("");

                        attributes.forEach(attr => {
                            const row = `
                                <div class="wr-trendyol-attr-row">
                                    <label>${attr.name}</label>
                                    <input type="text" name="wr_trendyol_attributes[${attr.id}]" value="" />
                                </div>
                            `;
                            box.append(row);
                        });
                    }

                    alert("Özellikler başarıyla yüklendi.");
                    return;
                }

                // ERROR CASE
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
