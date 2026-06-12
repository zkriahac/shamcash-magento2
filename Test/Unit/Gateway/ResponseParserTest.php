<?php
/**
 * Unit tests for the Sham Cash response-envelope parser.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Test\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use ShamCash\Payment\Gateway\Exception\ApiException;
use ShamCash\Payment\Gateway\Exception\AuthenticationException;
use ShamCash\Payment\Gateway\Exception\FetchFailedException;
use ShamCash\Payment\Gateway\Exception\RateLimitException;
use ShamCash\Payment\Gateway\Exception\SubscriptionUnavailableException;
use ShamCash\Payment\Gateway\Http\HttpResponse;
use ShamCash\Payment\Gateway\ResponseParser;

class ResponseParserTest extends TestCase
{
    private ResponseParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ResponseParser();
    }

    private function response(array $body, int $status = 200, array $headers = []): HttpResponse
    {
        return new HttpResponse($status, json_encode($body), $headers);
    }

    public function testSuccessReturnsData(): void
    {
        $response = $this->response([
            'status' => 'success',
            'code' => 'SUCCESS',
            'message' => 'ok',
            'data' => [['id' => 'acc_1']],
        ]);
        self::assertSame([['id' => 'acc_1']], $this->parser->parse($response));
    }

    public function testAuthInvalidThrowsAuthenticationException(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->parser->parse($this->response([
            'status' => 'error',
            'code' => 'AUTH_INVALID',
            'message' => 'Token revoked',
            'data' => null,
        ], 401));
    }

    public function testSubscriptionUnavailableThrowsTypedException(): void
    {
        $this->expectException(SubscriptionUnavailableException::class);
        $this->parser->parse($this->response([
            'status' => 'error',
            'code' => 'SUBSCRIPTION_UNAVAILABLE',
            'message' => 'No active subscription',
            'data' => null,
        ], 403));
    }

    public function testRateLimitCapturesRetryAfterAndIsRetryable(): void
    {
        try {
            $this->parser->parse($this->response(
                ['status' => 'error', 'code' => 'RATE_LIMIT_EXCEEDED', 'message' => 'slow down', 'data' => null],
                429,
                ['retry-after' => '7']
            ));
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(7, $e->getRetryAfter());
            self::assertTrue($e->isRetryable());
        }
    }

    public function testFetchFailedIsRetryable(): void
    {
        try {
            $this->parser->parse($this->response([
                'status' => 'error',
                'code' => 'FETCH_FAILED',
                'message' => 'upstream error',
                'data' => null,
            ], 502));
            self::fail('Expected FetchFailedException');
        } catch (FetchFailedException $e) {
            self::assertTrue($e->isRetryable());
            self::assertSame('FETCH_FAILED', $e->getApiCode());
        }
    }

    public function testValidationErrorIsGenericApiExceptionAndNotRetryable(): void
    {
        try {
            $this->parser->parse($this->response([
                'status' => 'error',
                'code' => 'VALIDATION_ERROR',
                'message' => 'bad date',
                'data' => null,
            ], 400));
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame('VALIDATION_ERROR', $e->getApiCode());
            self::assertFalse($e->isRetryable());
        }
    }

    public function testUnreadableServerErrorIsTreatedAsRetryable(): void
    {
        $this->expectException(FetchFailedException::class);
        $this->parser->parse(new HttpResponse(503, '<html>gateway down</html>'));
    }

    public function testUnreadableClientErrorIsFatal(): void
    {
        $this->expectException(ApiException::class);
        $this->parser->parse(new HttpResponse(400, 'not json'));
    }
}
