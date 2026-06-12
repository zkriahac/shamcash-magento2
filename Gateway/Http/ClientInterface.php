<?php
/**
 * HTTP transport contract for the Sham Cash gateway.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway\Http;

/**
 * Abstracts the GET transport so {@see \ShamCash\Payment\Gateway\ApiClient}
 * can be unit-tested with a fake. The Sham Cash API is read-only, so only GET
 * is required.
 */
interface ClientInterface
{
    /**
     * Perform a GET request.
     *
     * @param string $url Fully-qualified URL including any query string.
     * @param array<string,string> $headers Header name => value.
     */
    public function get(string $url, array $headers): HttpResponse;
}
