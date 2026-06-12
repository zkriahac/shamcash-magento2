<?php
/**
 * Unit tests for the typed configuration accessor.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ShamCash\Payment\Model\Config;

class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;
    private EncryptorInterface&MockObject $encryptor;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->encryptor = $this->createMock(EncryptorInterface::class);
        $this->config = new Config($this->scopeConfig, $this->encryptor);
    }

    /**
     * @param array<string,string> $values keyed by config field name.
     */
    private function withValues(array $values): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static function (string $path) use ($values) {
                $field = substr($path, strrpos($path, '/') + 1);
                return $values[$field] ?? null;
            }
        );
    }

    public function testCoinMapParsesCommaAndNewlineSeparated(): void
    {
        $this->withValues(['coin_map' => "SYP:1, USD:2\nTRY:3"]);
        self::assertSame('1', $this->config->getCoinId('SYP'));
        self::assertSame('2', $this->config->getCoinId('usd'));
        self::assertSame('3', $this->config->getCoinId('TRY'));
        self::assertNull($this->config->getCoinId('EUR'));
    }

    public function testAllowedCurrenciesDefaultsWhenEmpty(): void
    {
        $this->withValues([]);
        self::assertSame(['SYP', 'USD', 'TRY'], $this->config->getAllowedCurrencies());
        self::assertTrue($this->config->isCurrencyAllowed('usd'));
        self::assertFalse($this->config->isCurrencyAllowed('EUR'));
    }

    public function testAllowedCurrenciesCustomList(): void
    {
        $this->withValues(['allowed_currencies' => 'syp, eur']);
        self::assertSame(['SYP', 'EUR'], $this->config->getAllowedCurrencies());
        self::assertTrue($this->config->isCurrencyAllowed('EUR'));
        self::assertFalse($this->config->isCurrencyAllowed('USD'));
    }

    public function testApiTokenIsDecrypted(): void
    {
        $this->withValues(['api_token' => 'cipher']);
        $this->encryptor->method('decrypt')->with('cipher')->willReturn('plain-token');
        self::assertSame('plain-token', $this->config->getApiToken());
    }

    public function testApiTokenEmptyStaysEmptyWithoutDecrypting(): void
    {
        $this->withValues(['api_token' => '']);
        $this->encryptor->expects(self::never())->method('decrypt');
        self::assertSame('', $this->config->getApiToken());
    }

    public function testApiBaseUrlDefaultAndTrailingSlashTrimmed(): void
    {
        $this->withValues([]);
        self::assertSame('https://api.shamcash-api.com/v1', $this->config->getApiBaseUrl());

        $this->setUp();
        $this->withValues(['api_base_url' => 'https://example.test/v1/']);
        self::assertSame('https://example.test/v1', $this->config->getApiBaseUrl());
    }

    public function testMatchingModeDefaultsToBoth(): void
    {
        $this->withValues([]);
        self::assertSame(Config::MATCH_MODE_BOTH, $this->config->getMatchingMode());
    }
}
