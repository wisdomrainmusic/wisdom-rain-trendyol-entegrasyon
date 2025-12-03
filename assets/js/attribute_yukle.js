jQuery(document).ready(function($){

    $('#wr_load_attributes_btn').on('click', function(){

        let category_id = $('#wr_trendyol_category').val();

        if (!category_id) {
            alert("Kategori seçiniz.");
            return;
        }

        console.log("Selected Trendyol Category ID:", category_id);

        $.post(ajaxurl, {
            action: 'wr_trendyol_load_attributes',
            category_id: category_id
        }, function(response){
            console.log(response);
        });
    });

});
