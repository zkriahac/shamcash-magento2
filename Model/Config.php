<?php
/**
 * Typed accessor for Sham Cash payment configuration.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads the payment/shamcash/* configuration written by etc/adminhtml/system.xml
 * and exposes it as typed values, decrypting the API token and parsing the
 * currency->coin_id map. This is the only class that knows config paths.
 */
class Config
{
    public const CODE = 'shamcash';

    private const XML_PATH = 'payment/shamcash/';

    public const MATCH_MODE_NOTE = 'note';
    public const MATCH_MODE_AMOUNT = 'amount';
    public const MATCH_MODE_BOTH = 'both';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isActive(?int $storeId = null): bool
    {
        return $this->flag('active', $storeId);
    }

    public function getTitle(?int $storeId = null): string
    {
        return $this->value('title', $storeId) ?: 'Sham Cash';
    }

    public function getApiBaseUrl(?int $storeId = null): string
    {
        return rtrim($this->value('api_base_url', $storeId) ?: 'https://api.shamcash-api.com/v1', '/');
    }

    /**
     * Decrypted API bearer token.
     */
    public function getApiToken(?int $storeId = null): string
    {
        $raw = (string)$this->value('api_token', $storeId);
        return $raw === '' ? '' : (string)$this->encryptor->decrypt($raw);
    }

    public function getAccountId(?int $storeId = null): string
    {
        return (string)$this->value('account_id', $storeId);
    }

    /**
     * Map an ISO currency code to the Sham Cash coin_id used for /transactions
     * filtering. Returns null when no mapping is configured (no filter applied).
     */
    public function getCoinId(string $currencyCode, ?int $storeId = null): ?string
    {
        $map = $this->getCoinMap($storeId);
        return $map[strtoupper($currencyCode)] ?? null;
    }

    /**
     * Parse the free-form map field, e.g. "SYP:1, USD:2, TRY:3".
     *
     * @return array<string,string>
     */
    public function getCoinMap(?int $storeId = null): array
    {
        $raw = (string)$this->value('coin_map', $storeId);
        $map = [];
        foreach (preg_split('/[,\n]/', $raw) ?: [] as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) === 2 && trim($parts[0]) !== '' && trim($parts[1]) !== '') {
                $map[strtoupper(trim($parts[0]))] = trim($parts[1]);
            }
        }
        return $map;
    }

    /**
     * @return string[] Allowed ISO currency codes (defaults to SYP/USD/TRY).
     */
    public function getAllowedCurrencies(?int $storeId = null): array
    {
        $raw = (string)$this->value('allowed_currencies', $storeId);
        $codes = array_filter(array_map('trim', explode(',', strtoupper($raw))));
        return $codes ?: ['SYP', 'USD', 'TRY'];
    }

    public function isCurrencyAllowed(string $currencyCode, ?int $storeId = null): bool
    {
        return in_array(strtoupper($currencyCode), $this->getAllowedCurrencies($storeId), true);
    }

    public function getMatchingMode(?int $storeId = null): string
    {
        return $this->value('matching_mode', $storeId) ?: self::MATCH_MODE_BOTH;
    }

    /**
     * Absolute amount tolerance when verifying a matched transfer.
     */
    public function getAmountTolerance(?int $storeId = null): string
    {
        $value = $this->value('amount_tolerance', $storeId);
        return $value !== null && $value !== '' ? (string)$value : '0';
    }

    /**
     * Minutes before order creation to widen the transaction search window
     * (covers clock skew / customers who paid just before placing the order).
     */
    public function getTimeWindowGraceMinutes(?int $storeId = null): int
    {
        return (int)$this->value('time_window_grace', $storeId);
    }

    /**
     * Maximum age (minutes) of a pending order still polled by the cron.
     */
    public function getOrderMaxAgeMinutes(?int $storeId = null): int
    {
        return max(1, (int)$this->value('order_max_age', $storeId) ?: 1440);
    }

    /**
     * Minimum seconds between customer-triggered checks for one order.
     */
    public function getCustomerCheckRateLimit(?int $storeId = null): int
    {
        return max(0, (int)$this->value('customer_check_rate_limit', $storeId));
    }

    public function getNewOrderStatus(?int $storeId = null): string
    {
        return $this->value('order_status', $storeId) ?: 'pending_payment';
    }

    public function getInstructions(?int $storeId = null): string
    {
        return (string)$this->value('instructions', $storeId);
    }

    public function isDebugEnabled(?int $storeId = null): bool
    {
        return $this->flag('debug', $storeId);
    }

    /**
     * Cached merchant wallet address, refreshed from /accounts in admin.
     */
    public function getAccountAddress(?int $storeId = null): string
    {
        return (string)$this->value('account_address', $storeId);
    }

    public function getAccountQrPayload(?int $storeId = null): string
    {
        return (string)$this->value('account_qr_payload', $storeId);
    }

    private function value(string $field, ?int $storeId): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH . $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value === null ? null : (string)$value;
    }

    private function flag(string $field, ?int $storeId): bool
    {
        return (bool)$this->scopeConfig->isSetFlag(
            self::XML_PATH . $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
