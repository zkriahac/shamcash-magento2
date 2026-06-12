<?php
/**
 * Supplies Sham Cash data to the Luma checkout (Knockout) renderer.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;
use ShamCash\Payment\Model\Config;

/**
 * Exposes the method title and a short pre-order note to the checkout payment
 * step. The full wallet/QR/reference instructions are shown after the order is
 * placed (success page), once a reference and amount exist.
 */
class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        try {
            $storeId = (int)$this->storeManager->getStore()->getId();
        } catch (\Throwable $e) {
            $storeId = null;
        }

        if (!$this->config->isActive($storeId)) {
            return [];
        }

        return [
            'payment' => [
                Config::CODE => [
                    'title' => $this->config->getTitle($storeId),
                    'checkoutNote' => (string)__(
                        'After you place the order, you will see the Sham Cash wallet and a payment reference to complete the transfer from your Sham Cash app.'
                    ),
                ],
            ],
        ];
    }
}
