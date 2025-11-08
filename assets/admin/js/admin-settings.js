jQuery(document).ready(function($) {
    $('.wpheka-save-changes').on('click', function() {
        var element = $(this);
        
        var fd = new FormData(document.getElementById('plugin-settings-form'));
        
        // Handle checkbox values
        fd.append('hide_price', $('input#hide_price').prop('checked') ? 'yes' : 'no');
        fd.append('hide_add_to_cart', $('input#hide_add_to_cart').prop('checked') ? 'yes' : 'no');
        fd.append('button_in_other_pages', $('input#button_in_other_pages').prop('checked') ? 'yes' : 'no');
        
        // Add AJAX action and nonce
        fd.append('action', 'save_wpheka_rfq_plugin_data');
        fd.append('wpheka_nonce', wpheka_admin_params.nonce);
        
        $.ajax({
            url: wpheka_admin_params.ajax_url,
            type: 'post',
            cache: false,
            processData: false,
            contentType: false,
            data: fd,
            success: function (response) {
                if (response.success) {
                    location.reload(true);
                }
            },
            error: function() {
                alert('An error occurred while saving settings.');
            }
        });
        
        return false;
    });
});