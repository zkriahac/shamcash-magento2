<?php
/**
 * Payment instructions service (Api implementation).
 */
declare(strict_types=1);

namespace ShamCash\Payment\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use ShamCash\Payment\Api\Data\PaymentInstructionsInterface as PaymentInstructionsData;
use ShamCash\Payment\Api\PaymentInstructionsInterface;

/**
 * Builds the wallet/QR/reference/amount bundle the storefront shows so the
 * customer can transfer the right amount with the order reference in the note.
 */
class PaymentInstructions implements PaymentInstructionsInterface
{
    public const STATUS_PAID = 'paid';
    public const STATUS_PENDING = 'pending';

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Config $config,
        private readonly PaymentInstructionsResultFactory $resultFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getForOrder(int $orderId): PaymentInstructionsData
    {
        $order = $this->orderRepository->get($orderId);
        $storeId = (int)$order->getStoreId();

        $reference = (string)$order->getIncrementId();
        $amount = number_format((float)$order->getGrandTotal(), 2, '.', '');
        $currency = (string)$order->getOrderCurrencyCode();
        $address = $this->config->getAccountAddress($storeId);

        return $this->resultFactory->create()
            ->setReference($reference)
            ->setAmount($amount)
            ->setCurrency($currency)
            ->setWalletAddress($address)
            ->setQrPayload($this->config->getAccountQrPayload($storeId))
            ->setStatus($this->resolveStatus($order))
            ->setInstructions($this->renderInstructions($storeId, $reference, $amount, $currency, $address));
    }

    /**
     * Render the configurable instruction text, substituting placeholders.
     */
    private function renderInstructions(
        int $storeId,
        string $reference,
        string $amount,
        string $currency,
        string $address
    ): string {
        $template = $this->config->getInstructions($storeId);
        if ($template === '') {
            return '';
        }
        return strtr($template, [
            '{reference}' => $reference,
            '{amount}' => $amount,
            '{currency}' => $currency,
            '{address}' => $address,
        ]);
    }

    private function resolveStatus(OrderInterface $order): string
    {
        $state = (string)$order->getState();
        $paidStates = [Order::STATE_PROCESSING, Order::STATE_COMPLETE, Order::STATE_CLOSED];
        return in_array($state, $paidStates, true) ? self::STATUS_PAID : self::STATUS_PENDING;
    }
}
