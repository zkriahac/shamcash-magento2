<?php
/**
 * Admin: "Check now" reconciliation for one order.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use ShamCash\Payment\Api\ReconciliationInterface;
use ShamCash\Payment\Reconciliation\MatchResult;

/**
 * Runs reconciliation on demand from the admin order view and reports the
 * outcome as an admin message.
 */
class Check extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'ShamCash_Payment::reconcile';

    public function __construct(
        Context $context,
        private readonly ReconciliationInterface $reconciliation
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');
        /** @var Redirect $redirect */
        $redirect = $this->resultRedirectFactory->create();
        $redirect->setPath('sales/order/view', ['order_id' => $orderId]);

        if ($orderId === 0) {
            $this->messageManager->addErrorMessage(__('Missing order id.'));
            return $redirect;
        }

        try {
            $result = $this->reconciliation->checkOrder($orderId);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Reconciliation failed: %1', $e->getMessage()));
            return $redirect;
        }

        $this->addOutcomeMessage($result->getStatus(), $result->getMessage());
        return $redirect;
    }

    private function addOutcomeMessage(string $status, string $message): void
    {
        switch ($status) {
            case MatchResult::STATUS_MATCHED:
            case MatchResult::STATUS_ALREADY_PAID:
                $this->messageManager->addSuccessMessage($message);
                break;
            case MatchResult::STATUS_ERROR:
            case MatchResult::STATUS_SUBSCRIPTION_UNAVAILABLE:
                $this->messageManager->addErrorMessage($message);
                break;
            default:
                $this->messageManager->addNoticeMessage($message);
        }
    }
}
