<?php
/**
 * GraphQL resolver: Sham Cash payment instructions for an order.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use ShamCash\Payment\Api\PaymentInstructionsInterface;
use ShamCash\Payment\Model\GraphQl\OrderAuthorization;

class PaymentInstructions implements ResolverInterface
{
    public function __construct(
        private readonly OrderAuthorization $orderAuthorization,
        private readonly PaymentInstructionsInterface $instructionsService
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
        $data = $this->instructionsService->getForOrder((int)$order->getId());

        return [
            'reference' => $data->getReference(),
            'amount' => $data->getAmount(),
            'currency' => $data->getCurrency(),
            'wallet_address' => $data->getWalletAddress(),
            'qr_payload' => $data->getQrPayload(),
            'status' => $data->getStatus(),
            'instructions' => $data->getInstructions(),
        ];
    }
}
