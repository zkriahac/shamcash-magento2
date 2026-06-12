<?php
/**
 * Parses the Sham Cash response envelope into data or typed exceptions.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway;

use ShamCash\Payment\Gateway\Exception\ApiException;
use ShamCash\Payment\Gateway\Exception\AuthenticationException;
use ShamCash\Payment\Gateway\Exception\FetchFailedException;
use ShamCash\Payment\Gateway\Exception\RateLimitException;
use ShamCash\Payment\Gateway\Exception\SubscriptionUnavailableException;
use ShamCash\Payment\Gateway\Http\HttpResponse;

/**
 * Every Sham Cash response uses the envelope { status, code, message, data }.
 * This turns a successful envelope into its `data` payload, and any error code
 * into the matching exception so callers branch on type rather than strings.
 *
 * @see https://api.shamcash-api.com/v1
 */
class ResponseParser
{
    /**
     * Validate the envelope and return its `data` payload.
     *
     * @return array<mixed> The decoded `data` field (object or list).
     * @throws ApiException
     */
    public function parse(HttpResponse $response): array
    {
        $decoded = json_decode($response->getBody(), true);

        if (!is_array($decoded) || !isset($decoded['status'])) {
            // No envelope at all — treat 5xx as transient, anything else as fatal.
            if ($response->getStatusCode() >= 500 || $response->getStatusCode() === 0) {
                throw new FetchFailedException(
                    __('The Sham Cash API returned an unreadable response (HTTP %1).', $response->getStatusCode()),
                    'FETCH_FAILED'
                );
            }
            throw new ApiException(
                __('The Sham Cash API returned an unexpected response (HTTP %1).', $response->getStatusCode()),
                'INTERNAL_ERROR'
            );
        }

        $code = (string)($decoded['code'] ?? 'INTERNAL_ERROR');
        $message = (string)($decoded['message'] ?? '');

        if (($decoded['status'] ?? null) === 'success' && $code === 'SUCCESS') {
            $data = $decoded['data'] ?? [];
            return is_array($data) ? $data : ['value' => $data];
        }

        throw $this->toException($code, $message, $response);
    }

    /**
     * Map a Sham Cash error code to the appropriate exception type.
     */
    private function toException(string $code, string $message, HttpResponse $response): ApiException
    {
        $phrase = $message !== '' ? __($message) : __('Sham Cash API error: %1', $code);

        return match ($code) {
            'AUTH_MISSING', 'AUTH_INVALID', 'FORBIDDEN' => new AuthenticationException($phrase, $code),
            'SUBSCRIPTION_UNAVAILABLE' => new SubscriptionUnavailableException($phrase, $code),
            'RATE_LIMIT_EXCEEDED' => new RateLimitException($phrase, $this->parseRetryAfter($response)),
            'FETCH_FAILED' => new FetchFailedException($phrase, $code),
            default => new ApiException($phrase, $code),
        };
    }

    /**
     * Read the Retry-After header (seconds) when present.
     */
    private function parseRetryAfter(HttpResponse $response): ?int
    {
        $value = $response->getHeader('Retry-After');
        if ($value === null || !is_numeric($value)) {
            return null;
        }
        return max(0, (int)$value);
    }
}
