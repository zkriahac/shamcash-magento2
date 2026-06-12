<?php
/**
 * Storefront "I've paid" check for the last placed order.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use ShamCash\Payment\Api\ReconciliationInterface;
use ShamCash\Payment\Model\Config;

/**
 * Lets the shopper trigger an immediate reconciliation for the order they just
 * placed. The order id is taken from the checkout session (never the request),
 * so a shopper can only check their own order. Rate-limited per order.
 */
class Check implements HttpGetActionInterface
{
    private const SESSION_LAST_CHECK_PREFIX = 'shamcash_last_check_';

    public function __construct(
        private readonly JsonFactory $resultJsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly ReconciliationInterface $reconciliation,
        private readonly Config $config
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        $orderId = (int)$this->checkoutSession->getLastOrderId();
        if ($orderId === 0) {
            return $result->setData([
                'status' => 'error',
                'message' => (string)__('We could not find your order in this session.'),
            ]);
        }

        if (!$this->passesRateLimit($orderId)) {
            return $result->setData([
                'status' => 'pending',
                'message' => (string)__('Please wait a moment before checking again.'),
            ]);
        }

        try {
            $outcome = $this->reconciliation->checkOrder($orderId);
        } catch (\Throwable $e) {
            return $result->setData([
                'status' => 'error',
                'message' => (string)__('We could not check your payment right now. Please try again shortly.'),
            ]);
        }

        return $result->setData([
            'status' => $outcome->getStatus(),
            'message' => $outcome->getMessage(),
        ]);
    }

    /**
     * Enforce the minimum interval between customer-triggered checks.
     */
    private function passesRateLimit(int $orderId): bool
    {
        $limit = $this->config->getCustomerCheckRateLimit();
        if ($limit <= 0) {
            return true;
        }
        $key = self::SESSION_LAST_CHECK_PREFIX . $orderId;
        $last = (int)$this->checkoutSession->getData($key);
        $now = time();
        if ($last > 0 && ($now - $last) < $limit) {
            return false;
        }
        $this->checkoutSession->setData($key, $now);
        return true;
    }
}
