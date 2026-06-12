<?php
/**
 * Resolves and authorizes a customer's order for GraphQL.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Model\GraphQl;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;

/**
 * Loads an order by increment id and verifies it belongs to the authenticated
 * customer, so GraphQL callers cannot read or reconcile someone else's order.
 * Guest orders are intentionally not addressable here (use the success page).
 */
class OrderAuthorization
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * @throws GraphQlAuthorizationException
     * @throws GraphQlNoSuchEntityException
     */
    public function getCustomerOrder(string $incrementId, int $customerId): OrderInterface
    {
        if ($customerId === 0) {
            throw new GraphQlAuthorizationException(
                __('The current customer is not authorized to access this order.')
            );
        }

        $criteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId)
            ->create();
        $orders = $this->orderRepository->getList($criteria)->getItems();
        $order = $orders ? reset($orders) : null;

        if (!$order instanceof OrderInterface || (int)$order->getCustomerId() !== $customerId) {
            throw new GraphQlNoSuchEntityException(__('Order "%1" was not found.', $incrementId));
        }

        return $order;
    }
}
