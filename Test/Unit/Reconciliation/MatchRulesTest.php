<?php
/**
 * Unit tests for the pure reconciliation matching rules.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Test\Unit\Reconciliation;

use PHPUnit\Framework\TestCase;
use ShamCash\Payment\Reconciliation\MatchRules;

class MatchRulesTest extends TestCase
{
    private MatchRules $rules;

    protected function setUp(): void
    {
        $this->rules = new MatchRules();
    }

    public function testReferenceCandidatesIncludesZeroStrippedForm(): void
    {
        self::assertSame(['000000123', '123'], $this->rules->referenceCandidates('000000123'));
        self::assertSame(['123'], $this->rules->referenceCandidates('123'));
    }

    public function testNoteMatchesIsCaseInsensitiveAndSubstring(): void
    {
        $references = $this->rules->referenceCandidates('000000123');
        self::assertTrue($this->rules->noteMatches('Order 000000123 payment', $references));
        self::assertTrue($this->rules->noteMatches('paying for 123', $references));
        self::assertFalse($this->rules->noteMatches('order 999', $references));
        self::assertFalse($this->rules->noteMatches('', $references));
        self::assertFalse($this->rules->noteMatches(null, $references));
    }

    public function testCurrencyMatchesIgnoresCaseAndWhitespace(): void
    {
        self::assertTrue($this->rules->currencyMatches('syp', 'SYP'));
        self::assertTrue($this->rules->currencyMatches(' USD ', 'usd'));
        self::assertFalse($this->rules->currencyMatches('USD', 'SYP'));
    }

    public function testAmountWithinToleranceExactMatch(): void
    {
        self::assertTrue($this->rules->amountWithinTolerance('1500.00', '1500', '0'));
        self::assertTrue($this->rules->amountWithinTolerance('1500', '1500.0000', '0'));
    }

    public function testAmountToleranceBoundaries(): void
    {
        // Within tolerance of 0.50
        self::assertTrue($this->rules->amountWithinTolerance('100.40', '100.00', '0.50'));
        self::assertTrue($this->rules->amountWithinTolerance('99.60', '100.00', '0.50'));
        // Exactly at the boundary
        self::assertTrue($this->rules->amountWithinTolerance('100.50', '100.00', '0.50'));
        // Outside tolerance
        self::assertFalse($this->rules->amountWithinTolerance('100.51', '100.00', '0.50'));
    }

    public function testAmountComparisonIsExactForLargeSypValues(): void
    {
        // Large amounts must not lose precision.
        self::assertTrue($this->rules->amountWithinTolerance('250000000.0000', '250000000', '0'));
        self::assertFalse($this->rules->amountWithinTolerance('250000000.0001', '250000000', '0'));
    }

    public function testNormalizeProducesFourDecimals(): void
    {
        self::assertSame('123.4000', $this->rules->normalize('123.4'));
        self::assertSame('1500.0000', $this->rules->normalize('1500'));
        self::assertSame('0.0000', $this->rules->normalize(''));
        self::assertSame('-5.2500', $this->rules->normalize('-5.25'));
    }
}
