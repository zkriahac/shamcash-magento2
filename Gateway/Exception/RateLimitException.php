<?php
/**
 * Sham Cash API rate limit hit.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway\Exception;

use Magento\Framework\Phrase;

/**
 * Raised for RATE_LIMIT_EXCEEDED. Honors the Retry-After header when present.
 */
class RateLimitException extends ApiException
{
    /** @var int|null Seconds to wait before retrying, from Retry-After. */
    private ?int $retryAfter;

    public function __construct(Phrase $phrase, ?int $retryAfter = null, ?\Throwable $cause = null)
    {
        parent::__construct($phrase, 'RATE_LIMIT_EXCEEDED', $cause);
        $this->retryAfter = $retryAfter;
    }

    /**
     * Seconds to wait before retrying, or null if the server gave no hint.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * @inheritDoc
     */
    public function isRetryable(): bool
    {
        return true;
    }
}
