<?php
/**
 * Admin order-view panel for Sham Cash reconciliation.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Block\Adminhtml\Order;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use ShamCash\Payment\Gateway\Dto\Transaction;
use ShamCash\Payment\Gateway\Exception\ApiException;
use ShamCash\Payment\Model\Config;
use ShamCash\Payment\Reconciliation\Matcher;

/**
 * Shows the Sham Cash payment status on the admin order view, a "Check now"
 * button, and — for unpaid orders — the recent transfers the operator can
 * manually credit (the manual override).
 */
class Info extends Template
{
    /** @var Transaction[]|null */
    private ?array $recentTransactions = null;
    private ?string $apiError = null;

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly Matcher $matcher,
        private readonly Config $config,
        private readonly FormKey $formKey,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Current admin form key for the manual-match form.
     */
    public function getFormKeyValue(): string
    {
        return $this->formKey->getFormKey();
    }

    public function getOrder(): ?Order
    {
        $order = $this->registry->registry('current_order') ?? $this->registry->registry('sales_order');
        return $order instanceof Order ? $order : null;
    }

    public function isShamCashOrder(): bool
    {
        $order = $this->getOrder();
        $payment = $order?->getPayment();
        return $payment !== null && $payment->getMethod() === Config::CODE;
    }

    public function isPaid(): bool
    {
        $order = $this->getOrder();
        if ($order === null) {
            return false;
        }
        return in_array(
            (string)$order->getState(),
            [Order::STATE_PROCESSING, Order::STATE_COMPLETE, Order::STATE_CLOSED],
            true
        );
    }

    public function getMatchedTransactionId(): string
    {
        $payment = $this->getOrder()?->getPayment();
        return $payment ? (string)$payment->getAdditionalInformation('shamcash_transaction_id') : '';
    }

    public function getCheckUrl(): string
    {
        return $this->getUrl('shamcash/order/check', ['order_id' => $this->getOrder()?->getId()]);
    }

    public function getManualMatchUrl(): string
    {
        return $this->getUrl('shamcash/order/manualmatch');
    }

    /**
     * @return Transaction[]
     */
    public function getRecentTransactions(): array
    {
        if ($this->recentTransactions !== null) {
            return $this->recentTransactions;
        }
        $this->recentTransactions = [];
        $order = $this->getOrder();
        if ($order === null) {
            return [];
        }
        try {
            $this->recentTransactions = $this->matcher->recentTransactions($order);
        } catch (ApiException $e) {
            $this->apiError = $e->getMessage();
        } catch (\Throwable $e) {
            $this->apiError = $e->getMessage();
        }
        return $this->recentTransactions;
    }

    public function getApiError(): ?string
    {
        return $this->apiError;
    }
}
