<?php
/**
 * Service that reconciles an order against Sham Cash transactions.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Api;

use ShamCash\Payment\Api\Data\ReconciliationResultInterface;

/**
 * Triggers a reconciliation check for one order. Backed by the same engine the
 * cron uses, and reused by the Luma controller, the GraphQL mutation and REST.
 *
 * @api
 */
interface ReconciliationInterface
{
    /**
     * Check whether the order's Sham Cash transfer has arrived and, if so,
     * confirm (invoice) the order.
     *
     * @param int $orderId
     * @return \ShamCash\Payment\Api\Data\ReconciliationResultInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function checkOrder(int $orderId): ReconciliationResultInterface;
}
