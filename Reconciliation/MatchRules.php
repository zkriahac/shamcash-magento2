<?php
/**
 * Pure matching rules for Sham Cash reconciliation.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Reconciliation;

/**
 * Framework-independent decision logic used by {@see Matcher}: how a transfer
 * note maps to an order reference, and how amounts/currencies are compared.
 *
 * Amounts are compared as fixed-point integers scaled to 4 decimals so there is
 * no floating-point rounding on large SYP values, and no dependency on the
 * bcmath extension. Kept free of Magento dependencies so it can be unit-tested
 * in isolation.
 */
class MatchRules
{
    private const SCALE = 4;

    /**
     * Order reference strings a customer might type in the transfer note
     * (the raw increment id and its zero-stripped form).
     *
     * @return string[]
     */
    public function referenceCandidates(string $incrementId): array
    {
        $candidates = [$incrementId];
        $trimmed = ltrim($incrementId, '0');
        if ($trimmed !== '' && $trimmed !== $incrementId) {
            $candidates[] = $trimmed;
        }
        return array_values(array_unique(array_filter($candidates, static fn ($v) => $v !== '')));
    }

    /**
     * Whether a transfer note references the order.
     *
     * @param string[] $references
     */
    public function noteMatches(?string $note, array $references): bool
    {
        if ($note === null || trim($note) === '') {
            return false;
        }
        foreach ($references as $reference) {
            if ($reference !== '' && stripos($note, $reference) !== false) {
                return true;
            }
        }
        return false;
    }

    public function currencyMatches(string $a, string $b): bool
    {
        return strtoupper(trim($a)) === strtoupper(trim($b));
    }

    /**
     * Whether |actual - expected| <= tolerance.
     */
    public function amountWithinTolerance(string $actual, string $expected, string $tolerance): bool
    {
        $diff = abs($this->toScaledInt($actual) - $this->toScaledInt($expected));
        return $diff <= abs($this->toScaledInt($tolerance));
    }

    /**
     * Normalize a decimal string to a fixed 4-decimal representation, e.g.
     * "123.4" -> "123.4000". Used when storing a matched amount.
     */
    public function normalize(string $amount): string
    {
        $scaled = $this->toScaledInt($amount);
        $negative = $scaled < 0;
        $digits = str_pad((string)abs($scaled), self::SCALE + 1, '0', STR_PAD_LEFT);
        $integer = substr($digits, 0, -self::SCALE);
        $fraction = substr($digits, -self::SCALE);
        return ($negative ? '-' : '') . $integer . '.' . $fraction;
    }

    /**
     * Convert a decimal amount string into an integer scaled to SCALE decimals.
     * Extra fractional digits are truncated; missing ones are zero-padded.
     */
    private function toScaledInt(string $amount): int
    {
        $amount = trim($amount);
        if ($amount === '') {
            return 0;
        }

        $negative = false;
        if ($amount[0] === '-') {
            $negative = true;
            $amount = substr($amount, 1);
        } elseif ($amount[0] === '+') {
            $amount = substr($amount, 1);
        }

        $parts = explode('.', $amount, 2);
        $integer = preg_replace('/\D/', '', $parts[0]) ?: '0';
        $fraction = isset($parts[1]) ? preg_replace('/\D/', '', $parts[1]) : '';
        $fraction = substr(str_pad($fraction, self::SCALE, '0'), 0, self::SCALE);

        $combined = ltrim($integer . $fraction, '0');
        $value = $combined === '' ? 0 : (int)$combined;

        return $negative ? -$value : $value;
    }
}
