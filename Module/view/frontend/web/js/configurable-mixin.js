define([
    'jquery',
    'mage/utils/wrapper' // Include Magento's wrapper utility
], function ($, wrapper) {
    'use strict';

    return function (configurable) {
        // Wrap the original _create method to add custom functionality
        configurable.prototype._create = wrapper.wrap(configurable.prototype._create, function (originalMethod) {
            originalMethod.call(this); // Call the original method
            this._checkMandatoryOptions();
        });

        // Add a new method to check if all mandatory options are filled
        configurable.prototype._checkMandatoryOptions = function () {
            var productOptions = $('.product-options-wrapper');

            productOptions.on('change', '.super-attribute-select', function () {
                var allOptionsFilled = true;

                productOptions.find('.super-attribute-select').each(function () {
                    if ($(this).val() === '') {
                        allOptionsFilled = false;
                    }
                });

                if (allOptionsFilled) {
                    $('#proceed-to-shipment').prop('disabled', false);
                } else {
                    $('#proceed-to-shipment').prop('disabled', true);
                }
                if (!allOptionsFilled) {
                    $('#shipping-entry').hide();
                } 
            });

        };
        return configurable;
    };

});
