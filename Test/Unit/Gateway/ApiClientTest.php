<?php
/**
 * Unit tests for the Sham Cash API client (retry/backoff + DTO mapping).
 */
declare(strict_types=1);

namespace ShamCash\Payment\Test\Unit\Gateway;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ShamCash\Payment\Gateway\ApiClient;
use ShamCash\Payment\Gateway\Exception\AuthenticationException;
use ShamCash\Payment\Gateway\Http\ClientInterface;
use ShamCash\Payment\Gateway\Http\HttpResponse;
use ShamCash\Payment\Gateway\ResponseParser;
use ShamCash\Payment\Model\Config;

class ApiClientTest extends TestCase
{
    private function envelope(string $code, array $data = [], string $status = 'success'): string
    {
        return (string)json_encode([
            'status' => $status,
            'code' => $code,
            'message' => $code,
            'data' => $data,
        ]);
    }

    /**
     * @param HttpResponse[] $queue Responses returned in order across calls.
     */
    private function fakeClient(array $queue): ClientInterface
    {
        return new class ($queue) implements ClientInterface {
            public int $calls = 0;

            /** @param HttpResponse[] $queue */
            public function __construct(private array $queue)
            {
            }

            public function get(string $url, array $headers): HttpResponse
            {
                $this->calls++;
                return array_shift($this->queue);
            }
        };
    }

    private function config(string $token = 'tok'): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => str_ends_with($path, '/api_token') && $token !== '' ? 'cipher' : null
        );
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturn($token);

        return new Config($scopeConfig, $encryptor);
    }

    private function client(ClientInterface $http, Config $config, int $maxAttempts = 3): ApiClient
    {
        return new ApiClient(
            $http,
            new ResponseParser(),
            $config,
            $this->createMock(LoggerInterface::class),
            $maxAttempts
        );
    }

    public function testTransactionsReturnsDtos(): void
    {
        $http = $this->fakeClient([
            new HttpResponse(200, $this->envelope('SUCCESS', [
                ['transaction_id' => 'tx1', 'amount' => '10', 'currency' => ['code' => 'SYP'], 'note' => 'Order 1'],
            ])),
        ]);

        $transactions = $this->client($http, $this->config())->transactions('acc_1');

        self::assertCount(1, $transactions);
        self::assertSame('tx1', $transactions[0]->getTransactionId());
        self::assertSame('SYP', $transactions[0]->getCurrencyCode());
    }

    public function testRetryableFailureThenSuccess(): void
    {
        $http = $this->fakeClient([
            new HttpResponse(502, $this->envelope('FETCH_FAILED', [], 'error')),
            new HttpResponse(200, $this->envelope('SUCCESS', [['id' => 'acc_1', 'status' => 'active']])),
        ]);

        $accounts = $this->client($http, $this->config(), 3)->accounts();

        self::assertSame(2, $http->calls);
        self::assertCount(1, $accounts);
        self::assertSame('acc_1', $accounts[0]->getId());
    }

    public function testNonRetryableErrorThrowsImmediately(): void
    {
        $http = $this->fakeClient([
            new HttpResponse(401, $this->envelope('AUTH_INVALID', [], 'error')),
            new HttpResponse(200, $this->envelope('SUCCESS')),
        ]);

        $client = $this->client($http, $this->config(), 3);

        try {
            $client->accounts();
            self::fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            self::assertSame(1, $http->calls, 'Must not retry a non-retryable error');
        }
    }

    public function testMissingTokenThrowsBeforeHttpCall(): void
    {
        $http = $this->fakeClient([new HttpResponse(200, $this->envelope('SUCCESS'))]);
        $client = $this->client($http, $this->config(''), 3);

        $this->expectException(AuthenticationException::class);
        try {
            $client->accounts();
        } finally {
            self::assertSame(0, $http->calls, 'No HTTP call should be made without a token');
        }
    }
}
