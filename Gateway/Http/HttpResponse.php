<?php
/**
 * Minimal HTTP response value object.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway\Http;

/**
 * Transport-agnostic response used by the Sham Cash gateway so the API client
 * stays testable without a live socket.
 */
class HttpResponse
{
    /**
     * @param array<string,string> $headers Lower-cased header name => value.
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly string $body,
        private readonly array $headers = []
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Case-insensitive header lookup.
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
