<?php
/**
 * Base exception for Sham Cash API failures.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway\Exception;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;

/**
 * Thrown when the Sham Cash API returns an error envelope or is unreachable.
 *
 * Carries the machine-readable {@see https://api.shamcash-api.com/v1} error
 * code (e.g. AUTH_INVALID, VALIDATION_ERROR) so callers can branch on it.
 */
class ApiException extends LocalizedException
{
    /** @var string */
    private string $apiCode;

    public function __construct(Phrase $phrase, string $apiCode = 'INTERNAL_ERROR', ?\Throwable $cause = null)
    {
        parent::__construct($phrase, $cause);
        $this->apiCode = $apiCode;
    }

    /**
     * Machine-readable Sham Cash error code.
     */
    public function getApiCode(): string
    {
        return $this->apiCode;
    }

    /**
     * Whether retrying the request with backoff may succeed.
     */
    public function isRetryable(): bool
    {
        return false;
    }
}
