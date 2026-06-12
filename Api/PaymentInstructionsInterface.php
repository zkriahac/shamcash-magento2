<?php
/**
 * Service that builds Sham Cash payment instructions for an order.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Api;

use ShamCash\Payment\Api\Data\PaymentInstructionsInterface as PaymentInstructionsData;

/**
 * @api
 */
interface PaymentInstructionsInterface
{
    /**
     * Build the payment instructions (wallet, QR, reference, amount, status)
     * for an order paid with Sham Cash.
     *
     * @param int $orderId
     * @return \ShamCash\Payment\Api\Data\PaymentInstructionsInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getForOrder(int $orderId): PaymentInstructionsData;
}
