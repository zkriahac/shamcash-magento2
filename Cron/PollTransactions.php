<?php
/**
 * Background reconciliation: poll Sham Cash for pending orders.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Cron;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;
use ShamCash\Payment\Model\Config;
use ShamCash\Payment\Reconciliation\Matcher;
use ShamCash\Payment\Reconciliation\MatchResult;

/**
 * Periodically reconciles still-pending Sham Cash orders. Selects orders paid
 * with Sham Cash that are still awaiting payment and were placed within the
 * configured max-age window, and runs the {@see Matcher} on each. Skips stores
 * whose subscription is unavailable to avoid hammering the API.
 */
class PollTransactions
{
    public function __construct(
        private readonly CollectionFactory $orderCollectionFactory,
        private readonly Matcher $matcher,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        $oldestAllowed = $this->oldestCreatedAt();

        $collection = $this->orderCollectionFactory->create();
        $collection->getSelect()->join(
            ['sop' => $collection->getTable('sales_order_payment')],
            'main_table.entity_id = sop.parent_id',
            []
        )->where('sop.method = ?', Config::CODE);

        $collection->addFieldToFilter('state', ['in' => [Order::STATE_NEW, Order::STATE_PENDING_PAYMENT]])
            ->addFieldToFilter('created_at', ['gteq' => $oldestAllowed]);

        $subscriptionExhaustedStores = [];

        /** @var Order $order */
        foreach ($collection as $order) {
            $storeId = (int)$order->getStoreId();
            if (!$this->config->isActive($storeId) || isset($subscriptionExhaustedStores[$storeId])) {
                continue;
            }

            // Per-store max age (collection used the global minimum cutoff).
            if (strtotime((string)$order->getCreatedAt()) < $this->storeCutoff($storeId)) {
                continue;
            }

            try {
                $result = $this->matcher->match($order);
            } catch (\Throwable $e) {
                $this->logger->error('[ShamCash] Cron match failed for order ' . $order->getIncrementId() . ': ' . $e->getMessage());
                continue;
            }

            if ($result->getStatus() === MatchResult::STATUS_SUBSCRIPTION_UNAVAILABLE) {
                $subscriptionExhaustedStores[$storeId] = true;
                $this->logger->warning('[ShamCash] Skipping store ' . $storeId . ' for this run: ' . $result->getMessage());
            }
        }
    }

    /**
     * Global cutoff (largest configured max age across stores) for the SQL
     * pre-filter; per-store age is re-checked in the loop.
     */
    private function oldestCreatedAt(): string
    {
        $maxMinutes = $this->config->getOrderMaxAgeMinutes();
        return gmdate('Y-m-d H:i:s', time() - ($maxMinutes * 60));
    }

    private function storeCutoff(int $storeId): int
    {
        return time() - ($this->config->getOrderMaxAgeMinutes($storeId) * 60);
    }
}
