<?php
/**
 * Turns a matched Sham Cash transaction into a paid/invoiced order.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Reconciliation;

use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use ShamCash\Payment\Gateway\Dto\Transaction;

/**
 * Registers an offline capture for the matched transfer and creates the
 * invoice, moving the order to processing. Kept separate from {@see Matcher}
 * so the "how do we mark an order paid" concern is reusable (cron, customer,
 * admin, manual override).
 */
class OrderPaymentProcessor
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly TransactionFactory $transactionFactory
    ) {
    }

    /**
     * Invoice the order against the matched transaction.
     *
     * @param OrderInterface&Order $order
     */
    public function process(OrderInterface $order, Transaction $transaction, string $matchedBy): void
    {
        $payment = $order->getPayment();
        if ($payment !== null) {
            $payment->setTransactionId($transaction->getTransactionId());
            $payment->setIsTransactionClosed(true);
            $payment->setAdditionalInformation('shamcash_transaction_id', $transaction->getTransactionId());
            $payment->setAdditionalInformation('shamcash_sender_name', (string)$transaction->getSenderName());
            $payment->setAdditionalInformation('shamcash_matched_by', $matchedBy);
        }

        $comment = __(
            'Sham Cash payment confirmed (transaction %1, matched by %2, sender: %3).',
            $transaction->getTransactionId(),
            $matchedBy,
            $transaction->getSenderName() ?: '—'
        );

        $dbTransaction = $this->transactionFactory->create();

        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
            $invoice->setTransactionId($transaction->getTransactionId());
            $invoice->register();

            $invoicedOrder = $invoice->getOrder();
            $invoicedOrder->setIsInProcess(true);
            $invoicedOrder->addCommentToStatusHistory($comment);

            $dbTransaction->addObject($invoice)->addObject($invoicedOrder);
        } else {
            // Nothing to invoice (e.g. virtual/zero or already invoiced) — just record.
            $order->setState(Order::STATE_PROCESSING)
                ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING));
            $order->addCommentToStatusHistory($comment);
            $dbTransaction->addObject($order);
        }

        $dbTransaction->save();
    }
}
