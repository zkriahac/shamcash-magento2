<?php
/**
 * Immutable representation of a Sham Cash balance row.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway\Dto;

/**
 * Mirrors the /balances object (BalanceRow): currency.code, available, blocked.
 */
class Balance
{
    public function __construct(
        private readonly string $currencyCode,
        private readonly string $available,
        private readonly string $blocked
    ) {
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $currency = $row['currency'] ?? null;
        $currencyCode = is_array($currency)
            ? (string)($currency['code'] ?? '')
            : (string)($row['currency_code'] ?? $currency ?? '');

        return new self(
            strtoupper($currencyCode),
            (string)($row['available'] ?? '0'),
            (string)($row['blocked'] ?? '0')
        );
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function getAvailable(): string
    {
        return $this->available;
    }

    public function getBlocked(): string
    {
        return $this->blocked;
    }
}
