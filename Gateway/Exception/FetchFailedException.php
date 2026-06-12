<?php
/**
 * Transient upstream/internal fetch failure.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway\Exception;

/**
 * Raised for FETCH_FAILED (and transport-level errors). Safe to retry with
 * exponential backoff.
 */
class FetchFailedException extends ApiException
{
    /**
     * @inheritDoc
     */
    public function isRetryable(): bool
    {
        return true;
    }
}
