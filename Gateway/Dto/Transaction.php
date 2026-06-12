<?php
/**
 * Immutable representation of a Sham Cash transaction object.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway\Dto;

/**
 * Mirrors the /transactions object documented at https://api.shamcash-api.com/v1
 * (IncomingTransaction): transaction_id, amount, currency.code, occurred_at,
 * sender_name, sender_address, receiver_name, note.
 */
class Transaction
{
    public function __construct(
        private readonly string $transactionId,
        private readonly string $amount,
        private readonly string $currencyCode,
        private readonly ?string $occurredAt,
        private readonly ?string $senderName,
        private readonly ?string $senderAddress,
        private readonly ?string $receiverName,
        private readonly ?string $note
    ) {
    }

    /**
     * Build from a decoded API row, tolerating both a nested currency object
     * ({"currency":{"code":"SYP"}}) and a flat currency_code field.
     *
     * @param array<string,mixed> $row
     */
    public static function fromArray(array $row): self
    {
        $currency = $row['currency'] ?? null;
        $currencyCode = is_array($currency)
            ? (string)($currency['code'] ?? '')
            : (string)($row['currency_code'] ?? $currency ?? '');

        return new self(
            (string)($row['transaction_id'] ?? $row['id'] ?? ''),
            (string)($row['amount'] ?? '0'),
            strtoupper($currencyCode),
            isset($row['occurred_at']) ? (string)$row['occurred_at'] : null,
            isset($row['sender_name']) ? (string)$row['sender_name'] : null,
            isset($row['sender_address']) ? (string)$row['sender_address'] : null,
            isset($row['receiver_name']) ? (string)$row['receiver_name'] : null,
            isset($row['note']) ? (string)$row['note'] : null
        );
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    /**
     * Raw amount string as returned by the API (kept as string to avoid float
     * rounding; compare with bccomp).
     */
    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function getOccurredAt(): ?string
    {
        return $this->occurredAt;
    }

    public function getSenderName(): ?string
    {
        return $this->senderName;
    }

    public function getSenderAddress(): ?string
    {
        return $this->senderAddress;
    }

    public function getReceiverName(): ?string
    {
        return $this->receiverName;
    }

    /**
     * Free-text memo attached to the transfer. Primary key for order matching.
     */
    public function getNote(): ?string
    {
        return $this->note;
    }
}
