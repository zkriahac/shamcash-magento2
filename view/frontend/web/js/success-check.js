/**
 * Handles the "I've paid" button on the Sham Cash success page: asks the server
 * to reconcile the order and reflects the outcome.
 */
define(['jquery', 'mage/translate'], function ($, $t) {
    'use strict';

    return function (config, element) {
        var $root = $(element),
            $button = $root.find('#shamcash-ive-paid'),
            $message = $root.find('.shamcash-pending-message');

        $button.on('click', function () {
            $button.prop('disabled', true);

            $.ajax({
                url: config.checkUrl,
                type: 'GET',
                dataType: 'json',
                showLoader: true
            }).done(function (response) {
                var status = response && response.status;

                if (status === 'matched' || status === 'already_paid') {
                    $message.removeClass('notice').addClass('success')
                        .text(response.message || $t('Payment received — your order is confirmed.'));
                    $button.hide();
                    return;
                }

                $message.text(response && response.message
                    ? response.message
                    : $t('We have not seen your transfer yet. Please try again shortly.'));
                $button.prop('disabled', false);
            }).fail(function () {
                $message.text($t('We could not check your payment right now. Please try again shortly.'));
                $button.prop('disabled', false);
            });
        });
    };
});
