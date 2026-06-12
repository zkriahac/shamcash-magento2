/**
 * Sham Cash payment method component for the Luma checkout.
 */
define([
    'Magento_Checkout/js/view/payment/default'
], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'ShamCash_Payment/payment/shamcash'
        },

        /**
         * Short note shown under the method before the order is placed.
         *
         * @return {String}
         */
        getCheckoutNote: function () {
            var config = window.checkoutConfig.payment[this.getCode()] || {};
            return config.checkoutNote || '';
        }
    });
});
