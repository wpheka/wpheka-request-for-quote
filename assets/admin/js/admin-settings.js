jQuery(document).ready(function($) {

    // "Make phone number required" only means anything while the phone field is
    // shown. It used to stay clickable with the phone field off, so a shop owner
    // could require a field the form never rendered. Unchecked as well as
    // disabled, so what is on screen is exactly what gets saved.
    var $showPhone     = $('#show_phone_field');
    var $phoneRequired = $('#phone_required');

    if ($showPhone.length && $phoneRequired.length) {
        var syncPhoneRequired = function() {
            var enabled = $showPhone.prop('checked');

            if (!enabled) {
                $phoneRequired.prop('checked', false);
            }

            $phoneRequired.prop('disabled', !enabled);
            $phoneRequired.closest('.wpheka-check').toggleClass('is-disabled', !enabled);
        };

        $showPhone.on('change', syncPhoneRequired);
        syncPhoneRequired();
    }

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

        // A rejected save returns HTTP 200 with success:false, so jQuery's error
        // handler never runs. Without this the button still animated to "Changes
        // Updated" and the reason the server sent back was never shown -- the
        // save looked like it had worked.
        var reportFailure = function(message) {
            $('#wpheka-rfq-save-notice').remove();

            $('<div/>', {
                id: 'wpheka-rfq-save-notice',
                'class': 'notice notice-error',
                css: { margin: '12px 0' }
            }).append($('<p/>').text(message)).insertBefore('#plugin-settings-form');

            element.removeClass('loading').removeAttr('style');
            element.find('.text').text(wpheka_admin_params.i18n_save_changes);
            element.find('.progress-bar').css({ width: '0px' });
            element.find('.progress').css({ bottom: '0px' });
        };

        $.ajax({
            url: wpheka_admin_params.ajax_url,
            type: 'post',
            cache: false,
            processData: false,
            contentType: false,
            data: fd,
            success: function (response) {
                if (response && response.success) {
                    location.reload(true);
                    return;
                }

                reportFailure(
                    (response && response.data && response.data.message)
                        ? response.data.message
                        : wpheka_admin_params.i18n_save_failed
                );
            },
            error: function() {
                reportFailure(wpheka_admin_params.i18n_save_failed);
            }
        });

        return false;
    });
});
