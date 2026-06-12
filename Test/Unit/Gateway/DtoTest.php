<?php
/**
 * Unit tests for the Sham Cash DTO factories.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Test\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use ShamCash\Payment\Gateway\Dto\Account;
use ShamCash\Payment\Gateway\Dto\Balance;
use ShamCash\Payment\Gateway\Dto\Transaction;

class DtoTest extends TestCase
{
    public function testTransactionFromNestedCurrency(): void
    {
        $tx = Transaction::fromArray([
            'transaction_id' => 'tx_9',
            'amount' => '1500.00',
            'currency' => ['code' => 'syp'],
            'occurred_at' => '2026-04-16T01:22:21+03:00',
            'sender_name' => 'Ali',
            'note' => 'Order 000000123',
        ]);

        self::assertSame('tx_9', $tx->getTransactionId());
        self::assertSame('1500.00', $tx->getAmount());
        self::assertSame('SYP', $tx->getCurrencyCode());
        self::assertSame('Ali', $tx->getSenderName());
        self::assertSame('Order 000000123', $tx->getNote());
    }

    public function testTransactionFromFlatCurrencyAndIdFallback(): void
    {
        $tx = Transaction::fromArray([
            'id' => 'tx_flat',
            'amount' => '10',
            'currency_code' => 'usd',
        ]);
        self::assertSame('tx_flat', $tx->getTransactionId());
        self::assertSame('USD', $tx->getCurrencyCode());
        self::assertNull($tx->getNote());
    }

    public function testBalanceFromArray(): void
    {
        $balance = Balance::fromArray([
            'currency' => ['code' => 'TRY'],
            'available' => '42.5',
            'blocked' => '1.0',
        ]);
        self::assertSame('TRY', $balance->getCurrencyCode());
        self::assertSame('42.5', $balance->getAvailable());
        self::assertSame('1.0', $balance->getBlocked());
    }

    public function testAccountFromArrayAndActiveFlag(): void
    {
        $account = Account::fromArray([
            'id' => 'acc_1',
            'status' => 'active',
            'address' => 'SC-WALLET-1',
            'qr_payload' => 'qr-data',
            'subscription_expires_at' => '2026-12-31',
        ]);
        self::assertSame('acc_1', $account->getId());
        self::assertTrue($account->isActive());
        self::assertSame('SC-WALLET-1', $account->getAddress());
        self::assertSame('qr-data', $account->getQrPayload());

        $inactive = Account::fromArray(['id' => 'acc_2', 'status' => 'inactive']);
        self::assertFalse($inactive->isActive());
    }
}
