<?php
/**
 * Admin: manually confirm an order against a chosen transaction.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use ShamCash\Payment\Model\Config;
use ShamCash\Payment\Reconciliation\Matcher;
use ShamCash\Payment\Reconciliation\MatchResult;

/**
 * The manual-override action: an operator picks a specific Sham Cash transfer
 * and credits it to the order, bypassing automatic note/amount checks (but
 * still guarded against double-crediting one transfer).
 */
class ManualMatch extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'ShamCash_Payment::reconcile';

    public function __construct(
        Context $context,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Matcher $matcher
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        $transactionId = trim((string)$this->getRequest()->getParam('transaction_id'));

        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('sales/order/view', ['order_id' => $orderId]);

        if ($orderId === 0 || $transactionId === '') {
            $this->messageManager->addErrorMessage(__('An order and a transaction are required.'));
            return $redirect;
        }

        try {
            $order = $this->orderRepository->get($orderId);
            $payment = $order->getPayment();
            if ($payment === null || $payment->getMethod() !== Config::CODE) {
                $this->messageManager->addErrorMessage(__('This is not a Sham Cash order.'));
                return $redirect;
            }
            $result = $this->matcher->manualMatch($order, $transactionId);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Manual match failed: %1', $e->getMessage()));
            return $redirect;
        }

        if ($result->getStatus() === MatchResult::STATUS_MATCHED
            || $result->getStatus() === MatchResult::STATUS_ALREADY_PAID) {
            $this->messageManager->addSuccessMessage($result->getMessage());
        } else {
            $this->messageManager->addErrorMessage($result->getMessage());
        }

        return $redirect;
    }
}
