<?php
/**
 * cURL-backed HTTP transport.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway\Http;

use Magento\Framework\HTTP\Client\CurlFactory;
use ShamCash\Payment\Gateway\Exception\FetchFailedException;

/**
 * Default {@see ClientInterface} implementation built on Magento's cURL client.
 * A fresh client is created per request via the factory so headers do not leak
 * between calls.
 */
class CurlClient implements ClientInterface
{
    private const TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly CurlFactory $curlFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(string $url, array $headers): HttpResponse
    {
        $curl = $this->curlFactory->create();
        $curl->setOption(CURLOPT_TIMEOUT, self::TIMEOUT_SECONDS);
        $curl->setHeaders($headers);

        try {
            $curl->get($url);
        } catch (\Throwable $e) {
            throw new FetchFailedException(
                __('Could not reach the Sham Cash API: %1', $e->getMessage()),
                'FETCH_FAILED',
                $e
            );
        }

        $normalized = [];
        foreach ($curl->getHeaders() as $key => $value) {
            $normalized[strtolower((string)$key)] = is_array($value) ? (string)reset($value) : (string)$value;
        }

        return new HttpResponse((int)$curl->getStatus(), (string)$curl->getBody(), $normalized);
    }
}
