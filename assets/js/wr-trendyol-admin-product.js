jQuery(function($){

    console.log("WR TRENDYOL PRODUCT JS ACTIVE");

    // ------------------------------------------------------------

    const btn = $('#wr_trendyol_load_attributes_btn');

    if (!btn.length) {
        console.warn("WR Trendyol: Load button not found.");
        return;
    }

    btn.on("click", function (e) {
        e.preventDefault();

        // Trendyol kategori dropdown'undan ID'yi al
        const category_id = $('#wr_trendyol_category_id').val() || null;
        const nonce = wr_trendyol_product_data.nonce;
        const post_id = wr_trendyol_product_data.post_id;

        if (!category_id || category_id.trim() === "") {
            alert("Lütfen bir Trendyol kategorisi seçin.");
            return;
        }

        console.log("WR TRENDYOL SELECTED CATEGORY ID:", category_id);

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
                console.log("WR ATTR RAW RESPONSE:", response);

                if (!response) {
                    alert("Sunucudan geçersiz yanıt alındı.");
                    return;
                }

                if (response.success === true) {

                    // ------------------------------------------------------------------
                    // 1) Payload'u normalize et
                    // ------------------------------------------------------------------
                    const payload    = response.data || [];
                    const attributes = Array.isArray(payload)
                        ? payload
                        : (payload.attributes || []);

                    console.log("WR ATTR NORMALIZED ATTRIBUTES:", attributes);

                    if (!attributes || !attributes.length) {
                        alert("Bu Trendyol kategorisi için zorunlu özellik bulunamadı.");
                        return;
                    }

                    // ------------------------------------------------------------------
                    // 2) Admin kutusuna HTML yaz
                    //    PHP tarafının beklediği name:
                    //    wr_trendyol_attr[ATTRIBUTE_ID][value_id][]
                    //    wr_trendyol_attr[ATTRIBUTE_ID][custom]
                    // ------------------------------------------------------------------
                    const box = $('#wr_trendyol_attributes_box');

                    if (!box.length) {
                        console.warn("WR Trendyol: #wr_trendyol_attributes_box bulunamadı.");
                        alert("Özellik alanı bulunamadı. Lütfen sayfayı yenileyin.");
                        return;
                    }

                    box.empty();

                    attributes.forEach(function (attr) {

                        const attrId   = attr.attributeId || attr.id;
                        const attrName = attr.attributeName || attr.name || '—';

                        const required      = !!(attr.required);
                        const multiple      = !!(attr.multipleValues || attr.multiple);
                        const allowCustom   = !!(attr.allowCustom || attr.customValue);
                        const values        = attr.attributeValues || attr.values || [];

                        if (!attrId) {
                            console.warn("WR Trendyol: attributeId eksik", attr);
                            return;
                        }

                        let fieldHtml = '';
                        const baseName = `wr_trendyol_attr[${attrId}]`;

                        if (values.length && !allowCustom) {
                            // Sadece hazır değerlerden seçim
                            const multipleAttr = multiple ? ' multiple' : '';
                            const requiredAttr = required ? ' required' : '';
                            const selectName   = `${baseName}[value_id][]`;

                            fieldHtml += `<select name="${selectName}"${multipleAttr}${requiredAttr}>`;

                            if (!multiple) {
                                fieldHtml += `<option value="">Seçiniz</option>`;
                            }

                            values.forEach(function (val) {
                                const valId   = val.id;
                                const valName = val.name || '';
                                if (!valId) { return; }
                                fieldHtml += `<option value="${valId}">${valName}</option>`;
                            });

                            fieldHtml += `</select>`;

                        } else {
                            // Özel metin alanı
                            const requiredAttr = required ? ' required' : '';
                            const inputName    = `${baseName}[custom]`;
                            fieldHtml += `<input type="text" name="${inputName}" value=""${requiredAttr} />`;
                        }

                        const requiredMark = required ? ' <span style="color:#d63638;">*</span>' : '';

                        const rowHtml = `
                            <div class="wr-trendyol-attr-row">
                                <label>
                                    ${attrName}${requiredMark}
                                </label>
                                ${fieldHtml}
                            </div>
                        `;

                        box.append(rowHtml);
                    });

                    alert("Özellikler başarıyla yüklendi.");
                    return;
                }

                // ------------------------------------------------------------------
                // ERROR CASE
                // ------------------------------------------------------------------
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
