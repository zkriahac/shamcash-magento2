<?php
/**
 * GraphQL resolver: trigger reconciliation for an order.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use ShamCash\Payment\Api\ReconciliationInterface;
use ShamCash\Payment\Model\GraphQl\OrderAuthorization;

class CheckPayment implements ResolverInterface
{
    public function __construct(
        private readonly OrderAuthorization $orderAuthorization,
        private readonly ReconciliationInterface $reconciliation
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $orderNumber = trim((string)($args['order_number'] ?? ''));
        if ($orderNumber === '') {
            throw new GraphQlInputException(__('Required parameter "order_number" is missing.'));
        }

        $order = $this->orderAuthorization->getCustomerOrder($orderNumber, (int)$context->getUserId());
        $result = $this->reconciliation->checkOrder((int)$order->getId());

        return [
            'status' => $result->getStatus(),
            'message' => $result->getMessage(),
            'transaction_id' => $result->getTransactionId(),
        ];
    }
}
