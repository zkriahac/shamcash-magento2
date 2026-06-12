/**
 * Registers the Sham Cash payment method renderer in the Luma checkout.
 */
define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'shamcash',
        component: 'ShamCash_Payment/js/view/payment/method-renderer/shamcash-method'
    });

    return Component.extend({});
});
