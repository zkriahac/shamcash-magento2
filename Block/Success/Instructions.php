<?php
/**
 * Renders Sham Cash payment instructions on the checkout success page.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Block\Success;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use ShamCash\Payment\Api\Data\PaymentInstructionsInterface;
use ShamCash\Payment\Api\PaymentInstructionsInterface as PaymentInstructionsService;
use ShamCash\Payment\Model\Config;
use ShamCash\Payment\Model\PaymentInstructions as InstructionsModel;

/**
 * Shown after an order is placed with Sham Cash: the wallet to transfer to, the
 * amount, the order reference for the note, and an "I've paid" button that
 * triggers an immediate reconciliation check.
 */
class Instructions extends Template
{
    private ?PaymentInstructionsInterface $instructions = null;
    private bool $loaded = false;

    public function __construct(
        Context $context,
        private readonly CheckoutSession $checkoutSession,
        private readonly PaymentInstructionsService $instructionsService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Whether the just-placed order was paid with Sham Cash.
     */
    public function isAvailable(): bool
    {
        return $this->getInstructions() !== null;
    }

    public function getInstructions(): ?PaymentInstructionsInterface
    {
        if ($this->loaded) {
            return $this->instructions;
        }
        $this->loaded = true;

        $order = $this->checkoutSession->getLastRealOrder();
        $payment = $order?->getPayment();
        if ($payment === null || $payment->getMethod() !== Config::CODE || !$order->getId()) {
            return null;
        }

        try {
            $this->instructions = $this->instructionsService->getForOrder((int)$order->getId());
        } catch (\Throwable $e) {
            $this->instructions = null;
        }
        return $this->instructions;
    }

    public function isPaid(): bool
    {
        $instructions = $this->getInstructions();
        return $instructions !== null && $instructions->getStatus() === InstructionsModel::STATUS_PAID;
    }

    public function getCheckUrl(): string
    {
        return $this->getUrl('shamcash/payment/check');
    }
}
