define([
    'ko',
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'mage/url',
    'mage/storage',
    'jquery',
    'mage/validation'
], function (ko, Component, customerData, url, storage, $) {
    'use strict';

    var validationRules = {
        'US': { // United States
            phone: /^[2-9]\d{2}-\d{3}-\d{4}$/, // Example: 123-456-7890
            postcode: /^\d{5}(?:[-\s]\d{4})?$/ // Example: 12345 or 12345-6789
        },
        'IN': { // India
            phone: /^[6-9]\d{9}$/, // Example: 9876543210
            postcode: /^\d{6}$/ // Example: 110001
        },
        'CA': { // Canada
            phone: /^\d{10}$/, // Example: 1234567890
            postcode: /^[A-Za-z]\d[A-Za-z] \d[A-Za-z]\d$/ // Example: A1A 1A1
        }
        // Add more country rules here...
    };

    return Component.extend({
        isShippingEntryVisible: ko.observable(false),
        isCustomerLoggedIn: ko.observable(false),
        savedAddresses: ko.observableArray([]),
        selectedAddress: ko.observable(null),

        newShippingAddress: {
            firstname: ko.observable(''),
            lastname: ko.observable(''),
            street: ko.observable(''),
            city: ko.observable(''),
            postcode: ko.observable(''),
            region: ko.observable(''),
            country_id: ko.observable('IN'),
            telephone: ko.observable('')
        },
        defaults: {
            template: 'Vendor_Module/shipping-address'
        },
        availableCountries: ko.observableArray([]),
        availableRegions: ko.observableArray([]), // Observable array for regions
        selectedCountry: ko.observable(''), // Selected country
        selectedRegion: ko.observable(''), // Selected region

        initialize: function () {
            this._super();
            this.loadCountries();
            this.selectedCountry.subscribe(this.countryChanged.bind(this));

            var customer = customerData.get('customer');
            this.isCustomerLoggedIn(!!customer().firstname);

            customer.subscribe(function (data) {
                if (data && data.firstname) {
                    this.isCustomerLoggedIn(true);
                    this.loadCustomerAddresses(); // Load addresses on login
                }
            }.bind(this));

            customerData.reload(['customer'], true);

            if (this.isCustomerLoggedIn()) {
                this.loadCustomerAddresses();
            }

            // Observe changes to selectedAddress and reinitialize validation only when it changes
            this.selectedAddress.subscribe(function(newValue) {
                this.reapplyValidation(); // Apply validation when selected address changes
            }.bind(this));
        },

        reapplyValidation: function () {
            // Reinitialize validation on the form
            var form = $('#product_addtocart_form');
            form.validation(); // Initialize the validation

            // Specifically apply validation if the new address is selected
            if (this.selectedAddress() === 'new') {
                form.find('[data-validate]').each(function() {
                    $(this).rules('add', {
                        required: true
                    });
                });
            } else {
                form.find('[data-validate]').each(function() {
                    $(this).rules('remove', 'required');
                });
            }
            form.validation('clearError'); // Clear any previous errors
            console.log("Reapplying validation");
        },

        toggleShippingEntry: function () {
            this.isShippingEntryVisible(!this.isShippingEntryVisible()); // Toggle visibility
        },

        loadCustomerAddresses: function () {
            var self = this;
            if (self.isCustomerLoggedIn()) {
                $.ajax({
                    url: '/rest/V1/customers/me', // Correct endpoint to fetch logged-in customer's data
                    type: 'GET',
                    success: function (response) {
                        if (response.addresses) {
                            var addresses = response.addresses;
                            addresses.forEach(function (address) {
                                address.formatted_address = self.formatAddress(address);
                            });
                            var allOptions = [
                                { id: '', formatted_address: 'Select a saved address...' },
                                { id: 'new', formatted_address: 'New Address' }
                            ].concat(addresses);
                            self.savedAddresses(allOptions);
                        } else {
                            self.addDefaultDropdownOptions();
                        }
                    },
                    error: function (error) {
                        console.error('Error fetching customer addresses:', error);
                    }
                });
            }
        },

        formatAddress: function (address) {
            return `${address.firstname} ${address.lastname}, ${address.street.join(' ')}, ${address.city}, ${address.postcode}`;
        },

        loadCountries: function () {
            var self = this;
            var serviceUrl = url.build('rest/V1/directory/countries');
            storage.get(serviceUrl).done(function (countries) {
                var allCountryOptions = [
                    { id: '', full_name_locale: 'Select your country...' }
                ].concat(countries);
                self.availableCountries(allCountryOptions);
            }).fail(function (error) {
                console.error('Error fetching countries:', error);
            });
        },

        countryChanged: function () {
            var countryId = this.selectedCountry();
            if (countryId) {
                this.loadRegions(countryId);
                this.applyCountrySpecificValidation(countryId);
            }
        },

        loadRegions: function (countryId) {
            var self = this;
            var serviceUrl = url.build(`rest/V1/directory/countries/${countryId}`);
            storage.get(serviceUrl).done(function (regions) {
                if (regions.available_regions) {
                    var allRegionOptions = [
                        { id: '', name: 'Select your region/state...' }
                    ].concat(regions.available_regions);
                    self.availableRegions(allRegionOptions);
                } else {
                    self.availableRegions([]);
                }
            }).fail(function (error) {
                console.error('Error fetching regions:', error);
            });
        },
        applyCountrySpecificValidation: function (countryId) {
            var form = $('#product_addtocart_form');
            form.validation(); // Initialize the validation

            var rules = validationRules[countryId];

            if (rules) {
                form.find('#telephone').rules('add', {
                    pattern: rules.phone,
                    messages: {
                        pattern: 'Please enter a valid phone number for ' + countryId
                    }
                });

                form.find('#postcode').rules('add', {
                    pattern: rules.postcode,
                    messages: {
                        pattern: 'Please enter a valid postal code for ' + countryId
                    }
                });
            } else {
                form.find('#telephone').rules('remove', 'pattern');
                form.find('#postcode').rules('remove', 'pattern');
            }
        },

        getShippingAddressData: function () {
            if (this.isCustomerLoggedIn()) {
                if (this.selectedAddress() != "new" && this.selectedAddress() != "") {
                    return ko.toJS(this.selectedAddress());
                }
                if (this.newShippingAddress) {
                    return ko.toJS(this.newShippingAddress);
                }
            }
        }
    });
});
