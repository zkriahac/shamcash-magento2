<?php
/**
 * High-level client for the read-only Sham Cash API.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway;

use Psr\Log\LoggerInterface;
use ShamCash\Payment\Gateway\Dto\Account;
use ShamCash\Payment\Gateway\Dto\Balance;
use ShamCash\Payment\Gateway\Dto\Transaction;
use ShamCash\Payment\Gateway\Exception\ApiException;
use ShamCash\Payment\Gateway\Http\ClientInterface;
use ShamCash\Payment\Model\Config;

/**
 * Wraps the three documented endpoints — /accounts, /balances, /transactions —
 * returning typed DTOs. Adds bounded retry-with-backoff for transient failures
 * (FETCH_FAILED, rate limits), honoring Retry-After.
 *
 * @see https://api.shamcash-api.com/v1
 */
class ApiClient
{
    private const MAX_ATTEMPTS = 3;
    private const BACKOFF_BASE_SECONDS = 1;
    private const MAX_BACKOFF_SECONDS = 8;

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly ResponseParser $responseParser,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly int $maxAttempts = self::MAX_ATTEMPTS
    ) {
    }

    /**
     * List the Sham Cash accounts linked to the configured token.
     *
     * @return Account[]
     * @throws ApiException
     */
    public function accounts(?int $storeId = null): array
    {
        $rows = $this->get('/accounts', [], $storeId);
        return array_map(static fn (array $row): Account => Account::fromArray($row), $this->rows($rows));
    }

    /**
     * Balances for one linked account.
     *
     * @return Balance[]
     * @throws ApiException
     */
    public function balances(string $accountId, ?int $storeId = null): array
    {
        $rows = $this->get('/balances', ['account_id' => $accountId], $storeId);
        return array_map(static fn (array $row): Balance => Balance::fromArray($row), $this->rows($rows));
    }

    /**
     * Query transactions for one account.
     *
     * @param array<string,scalar> $filters start_at, end_at, coin_id, limit, ...
     * @return Transaction[]
     * @throws ApiException
     */
    public function transactions(string $accountId, array $filters = [], ?int $storeId = null): array
    {
        $query = array_merge(['account_id' => $accountId], array_filter(
            $filters,
            static fn ($v): bool => $v !== null && $v !== ''
        ));
        $rows = $this->get('/transactions', $query, $storeId);
        return array_map(static fn (array $row): Transaction => Transaction::fromArray($row), $this->rows($rows));
    }

    /**
     * Execute a GET with retry/backoff and return the decoded `data` payload.
     *
     * @param array<string,scalar> $query
     * @return array<mixed>
     * @throws ApiException
     */
    private function get(string $path, array $query, ?int $storeId): array
    {
        $token = $this->config->getApiToken($storeId);
        if ($token === '') {
            throw new Exception\AuthenticationException(
                __('No Sham Cash API token is configured.'),
                'AUTH_MISSING'
            );
        }

        $url = $this->config->getApiBaseUrl($storeId) . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];

        $attempt = 0;
        $lastError = null;
        while ($attempt < max(1, $this->maxAttempts)) {
            $attempt++;
            try {
                $response = $this->httpClient->get($url, $headers);
                $data = $this->responseParser->parse($response);
                $this->debug(sprintf('GET %s -> success (attempt %d)', $path, $attempt), $storeId);
                return $data;
            } catch (ApiException $e) {
                $lastError = $e;
                if (!$e->isRetryable() || $attempt >= $this->maxAttempts) {
                    throw $e;
                }
                $this->sleepBeforeRetry($e, $attempt);
            }
        }

        // Unreachable in practice, but keeps the type checker happy.
        throw $lastError ?? new ApiException(__('Sham Cash request failed.'));
    }

    /**
     * Backoff between retries, honoring a server-provided Retry-After.
     */
    private function sleepBeforeRetry(ApiException $e, int $attempt): void
    {
        $seconds = self::BACKOFF_BASE_SECONDS * (2 ** ($attempt - 1));
        if ($e instanceof Exception\RateLimitException && $e->getRetryAfter() !== null) {
            $seconds = $e->getRetryAfter();
        }
        $seconds = min($seconds, self::MAX_BACKOFF_SECONDS);
        if ($seconds > 0) {
            sleep($seconds);
        }
    }

    /**
     * Normalize a `data` payload to a list of rows (handles single-object data).
     *
     * @param array<mixed> $data
     * @return array<int,array<string,mixed>>
     */
    private function rows(array $data): array
    {
        if ($data === []) {
            return [];
        }
        // A list of objects vs a single object.
        if (array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }
        return [$data];
    }

    private function debug(string $message, ?int $storeId): void
    {
        if ($this->config->isDebugEnabled($storeId)) {
            $this->logger->debug('[ShamCash] ' . $message);
        }
    }
}
