<?php
/**
 * Outcome of a reconciliation attempt for a single order.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Reconciliation;

/**
 * Immutable result returned by {@see Matcher}. `status` is one of the STATUS_*
 * constants; callers branch on it for UI/logging.
 */
class MatchResult
{
    public const STATUS_MATCHED = 'matched';
    public const STATUS_ALREADY_PAID = 'already_paid';
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUBSCRIPTION_UNAVAILABLE = 'subscription_unavailable';
    public const STATUS_ERROR = 'error';
    public const STATUS_SKIPPED = 'skipped';

    private function __construct(
        private readonly string $status,
        private readonly string $message,
        private readonly ?string $transactionId = null
    ) {
    }

    public static function matched(string $transactionId, string $message): self
    {
        return new self(self::STATUS_MATCHED, $message, $transactionId);
    }

    public static function alreadyPaid(string $message): self
    {
        return new self(self::STATUS_ALREADY_PAID, $message);
    }

    public static function pending(string $message): self
    {
        return new self(self::STATUS_PENDING, $message);
    }

    public static function subscriptionUnavailable(string $message): self
    {
        return new self(self::STATUS_SUBSCRIPTION_UNAVAILABLE, $message);
    }

    public static function error(string $message): self
    {
        return new self(self::STATUS_ERROR, $message);
    }

    public static function skipped(string $message): self
    {
        return new self(self::STATUS_SKIPPED, $message);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function isMatched(): bool
    {
        return $this->status === self::STATUS_MATCHED;
    }
}
