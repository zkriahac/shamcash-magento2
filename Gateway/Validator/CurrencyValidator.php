<?php
/**
 * Availability validator restricting Sham Cash to supported currencies.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Quote\Api\Data\CartInterface;
use ShamCash\Payment\Model\Config;

/**
 * Wired as the payment adapter's "availability" validator: the method is hidden
 * at checkout when the quote currency is not in the configured allow-list
 * (defaults to SYP/USD/TRY), since the merchant can only reconcile transfers in
 * currencies Sham Cash supports.
 */
class CurrencyValidator extends AbstractValidator
{
    public function __construct(
        ResultInterfaceFactory $resultFactory,
        private readonly Config $config
    ) {
        parent::__construct($resultFactory);
    }

    /**
     * @param array<string,mixed> $validationSubject
     */
    public function validate(array $validationSubject): ResultInterface
    {
        $quote = $validationSubject['quote'] ?? null;
        if (!$quote instanceof CartInterface) {
            // No quote in context (e.g. admin availability probe) — allow.
            return $this->createResult(true);
        }

        $storeId = (int)$quote->getStoreId();
        $currency = (string)$quote->getQuoteCurrencyCode();

        if ($currency !== '' && !$this->config->isCurrencyAllowed($currency, $storeId)) {
            return $this->createResult(false, [
                __('Sham Cash is not available for the %1 currency.', $currency),
            ]);
        }

        return $this->createResult(true);
    }
}
