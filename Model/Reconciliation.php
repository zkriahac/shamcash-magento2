<?php
/**
 * Reconciliation service (Api implementation).
 */
declare(strict_types=1);

namespace ShamCash\Payment\Model;

use Magento\Sales\Api\OrderRepositoryInterface;
use ShamCash\Payment\Api\Data\ReconciliationResultInterface;
use ShamCash\Payment\Api\ReconciliationInterface;
use ShamCash\Payment\Reconciliation\Matcher;
use ShamCash\Payment\Reconciliation\MatchResult;

/**
 * Thin orchestrator: load the order, guard that it is a Sham Cash order, run
 * the {@see Matcher}, and adapt the internal {@see MatchResult} to the public
 * {@see ReconciliationResultInterface} returned over REST/GraphQL.
 */
class Reconciliation implements ReconciliationInterface
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Matcher $matcher,
        private readonly ReconciliationResultFactory $resultFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function checkOrder(int $orderId): ReconciliationResultInterface
    {
        $order = $this->orderRepository->get($orderId);

        $payment = $order->getPayment();
        if ($payment === null || $payment->getMethod() !== Config::CODE) {
            return $this->resultFactory->create()
                ->setStatus(MatchResult::STATUS_SKIPPED)
                ->setMessage((string)__('Order is not a Sham Cash order.'));
        }

        $result = $this->matcher->match($order);

        return $this->resultFactory->create()
            ->setStatus($result->getStatus())
            ->setMessage($result->getMessage())
            ->setTransactionId($result->getTransactionId());
    }
}
