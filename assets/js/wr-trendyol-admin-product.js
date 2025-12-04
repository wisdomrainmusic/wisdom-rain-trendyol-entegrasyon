jQuery(function ($) {

    console.log("WR TRENDYOL PRODUCT JS ACTIVE");

    const btn = $('#wr_trendyol_load_attributes_btn');

    if (!btn.length) {
        console.warn("WR Trendyol: load button not found.");
        return;
    }

    btn.on("click", function (e) {
        e.preventDefault();

        // DOĞRU CATEGORY DROPDOWN
        const category_id = $('#wr_trendyol_category_id').val() || null;

        const nonce = wr_trendyol_product_data.nonce;
        const post_id = wr_trendyol_product_data.post_id;

        if (!category_id) {
            alert("Lütfen bir Trendyol kategorisi seçin.");
            return;
        }

        btn.prop("disabled", true).text("Yükleniyor...");

        $.ajax({
            url: wr_trendyol_product_data.ajaxurl,
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

                if (!response || !response.success) {
                    alert(response?.data?.message || "Trendyol attribute hatası.");
                    return;
                }

                const attributes = response.data.attributes;

                if (!attributes || !attributes.length) {
                    alert("Bu kategori için Trendyol tarafından zorunlu özellik bulunmuyor.");
                    return;
                }

                console.log("WR NORMALIZED ATTRIBUTES:", attributes);

                const box = $("#wr_trendyol_attributes_box");
                if (!box.length) {
                    alert("Attribute kutusu bulunamadı. HTML DOM eksik.");
                    return;
                }

                box.html("");

                attributes.forEach(attr => {

                    const id = attr.attributeId || attr.id;
                    const name = attr.attributeName || attr.name || "Undefined Attribute";

                    const values = attr.attributeValues || [];

                    if (!id || !name) {
                        console.warn("WR Trendyol: attribute eksik", attr);
                        return;
                    }

                    let html = `<p class="form-field">
                        <label>${name}</label>`;

                    // SELECT VAR MI?
                    if (values.length > 0) {

                        html += `<select name="wr_trendyol_attr[${id}][value_id][]" class="wc-enhanced-select" style="max-width:260px;">`;
                        html += `<option value="">Seçin…</option>`;

                        values.forEach(v => {
                            const vid = v.id || v.attributeValueId;
                            const vname = v.name || v.attributeValue;
                            html += `<option value="${vid}">${vname}</option>`;
                        });

                        html += `</select>`;

                    } else {
                        // TEXT INPUT
                        html += `<input type="text" name="wr_trendyol_attr[${id}][custom]" placeholder="Özel değer" style="max-width:260px;"/>`;
                    }

                    html += `</p>`;

                    box.append(html);
                });

                alert("Özellikler başarıyla yüklendi.");
            },
            error: function (xhr, status) {
                console.error("WR AJAX ERROR:", status, xhr.responseText);
                alert("Trendyol bağlantı hatası. Tekrar deneyin.");
            },
            complete: function () {
                btn.prop("disabled", false).text("Özellikleri Yükle");
            }
        });

    });

});
