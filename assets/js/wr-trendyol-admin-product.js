jQuery(function ($) {

    console.log("WR TRENDYOL PRODUCT JS ACTIVE");

    if (typeof wr_trendyol_product_data === 'undefined') {
        console.warn('WR Trendyol: localized product data bulunamadı.');
        return;
    }

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

/********************************************************************
 * TRENDYOL PRODUCT PUSH – Ürünü Trendyol’a Gönder
 ********************************************************************/
jQuery(function ($) {

    console.log("WR TRENDYOL PUSH MODULE ACTIVE");

    if (typeof wr_trendyol_product_data === 'undefined') {
        console.warn('WR Trendyol: localized push data bulunamadı.');
        return;
    }

    const pushBtn = $("#wr_trendyol_push_product_btn");
    const resultBox = $("#wr_trendyol_push_result");

    if (!pushBtn.length) {
        console.warn("WR Trendyol: push button not found.");
        return;
    }

    pushBtn.on("click", function (e) {
        e.preventDefault();

        const product_id = $(this).data("product-id");
        console.log('WR Trendyol push product_id attribute:', product_id);
        const nonce = wr_trendyol_product_data.nonce;

        if (!product_id) {
            alert("Ürün ID bulunamadı.");
            return;
        }

        pushBtn.prop("disabled", true).text("Gönderiliyor...");

        $.ajax({
            url: wr_trendyol_product_data.ajaxurl,
            method: "POST",
            dataType: "json",
            data: {
                action: "wr_trendyol_push_product",
                product_id: product_id,
                nonce: nonce
            },
            success: function (response) {
                console.log("WR PUSH RESPONSE:", response);

                if (!response || !response.success) {
                    const msg = response?.data?.message || "Ürün Trendyol'a gönderilemedi.";
                    alert(msg);
                    resultBox.text(msg).css("color", "#d63638");
                    return;
                }

                const msg = response.data.message || "Ürün başarıyla gönderildi.";
                alert(msg);
                resultBox.text(msg).css("color", "#1d9d55");
            },
            error: function (xhr, status) {
                console.error("WR PUSH AJAX ERROR:", status, xhr.responseText);
                alert("Trendyol API hata verdi. Logları kontrol edin.");
                resultBox.text("API bağlantı hatası").css("color", "#d63638");
            },
            complete: function () {
                pushBtn.prop("disabled", false).text("Ürünü Trendyol'a Gönder");
            }
        });

    });
});

