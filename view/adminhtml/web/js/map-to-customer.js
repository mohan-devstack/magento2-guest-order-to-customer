define([
    'jquery',
    'Magento_Ui/js/modal/confirm',
    'mage/translate'
], function ($, confirm) {
    'use strict';

    return function (config, element) {
        $(element).on('click', function () {
            confirm({
                title: $.mage.__('Map to Customer'),
                content: $('<div>').text(config.message).html(),
                actions: {
                    /**
                     * Submit the mapping as a POST with the admin form key.
                     */
                    confirm: function () {
                        // Block a second submit while the page reloads.
                        $(element).prop('disabled', true);
                        $('body').trigger('processStart');

                        $('<form>', {
                            action: config.url,
                            method: 'POST'
                        }).append($('<input>', {
                            type: 'hidden',
                            name: 'form_key',
                            value: window.FORM_KEY
                        })).appendTo('body').trigger('submit');
                    }
                }
            });

            return false;
        });
    };
});
