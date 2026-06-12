<?php
/**
 * Result of a reconciliation check.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Api\Data;

/**
 * @api
 */
interface ReconciliationResultInterface
{
    public const STATUS = 'status';
    public const MESSAGE = 'message';
    public const TRANSACTION_ID = 'transaction_id';

    /**
     * One of matched, pending, already_paid, subscription_unavailable, error.
     */
    public function getStatus(): string;

    public function setStatus(string $status): self;

    public function getMessage(): string;

    public function setMessage(string $message): self;

    public function getTransactionId(): ?string;

    public function setTransactionId(?string $transactionId): self;
}
