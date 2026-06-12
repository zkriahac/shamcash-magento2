<?php
/**
 * Matches incoming Sham Cash transfers to pending orders.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Reconciliation;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use ShamCash\Payment\Gateway\ApiClient;
use ShamCash\Payment\Gateway\Dto\Transaction;
use ShamCash\Payment\Gateway\Exception\ApiException;
use ShamCash\Payment\Gateway\Exception\SubscriptionUnavailableException;
use ShamCash\Payment\Model\Config;
use ShamCash\Payment\Model\ResourceModel\PaymentMatch as PaymentMatchResource;

/**
 * Reconciliation engine. For a pending Sham Cash order it queries the
 * merchant's /transactions, finds the matching incoming transfer
 * (note/reference first, then amount+currency fallback), atomically claims the
 * transaction, and invoices the order via {@see OrderPaymentProcessor}.
 *
 * The same instance backs the cron, the customer "I've paid" action, and the
 * admin "Check now" button.
 */
class Matcher
{
    private const DEFAULT_LIMIT = 100;

    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly Config $config,
        private readonly PaymentMatchResource $matchResource,
        private readonly OrderPaymentProcessor $paymentProcessor,
        private readonly MatchRules $rules,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Attempt to reconcile a single order.
     *
     * @param OrderInterface&Order $order
     */
    public function match(OrderInterface $order): MatchResult
    {
        if ($this->isAlreadyPaid($order)) {
            return MatchResult::alreadyPaid((string)__('Order is already paid.'));
        }

        $storeId = (int)$order->getStoreId();
        $accountId = $this->config->getAccountId($storeId);
        if ($accountId === '') {
            return MatchResult::error((string)__('No Sham Cash account is configured.'));
        }

        $currency = (string)$order->getOrderCurrencyCode();
        $expectedAmount = $this->rules->normalize((string)$order->getGrandTotal());

        try {
            $transactions = $this->apiClient->transactions(
                $accountId,
                [
                    'start_at' => $this->windowStart($order, $storeId),
                    'end_at' => $this->now(),
                    'coin_id' => $this->config->getCoinId($currency, $storeId),
                    'limit' => self::DEFAULT_LIMIT,
                ],
                $storeId
            );
        } catch (SubscriptionUnavailableException $e) {
            $this->logger->warning('[ShamCash] Subscription unavailable: ' . $e->getMessage());
            return MatchResult::subscriptionUnavailable($e->getMessage());
        } catch (ApiException $e) {
            $this->logger->error('[ShamCash] Reconciliation API error: ' . $e->getMessage());
            return MatchResult::error($e->getMessage());
        }

        $candidate = $this->selectCandidate($order, $transactions, $currency, $expectedAmount, $storeId);
        if ($candidate === null) {
            return MatchResult::pending((string)__('No matching Sham Cash transfer found yet.'));
        }

        [$transaction, $matchedBy] = $candidate;

        return $this->claimAndInvoice($order, $transaction, $matchedBy);
    }

    /**
     * Admin override: confirm an order against a specific transaction id the
     * operator chose, bypassing note/amount checks but still preventing a
     * transfer from being credited to two orders.
     *
     * @param OrderInterface&Order $order
     */
    public function manualMatch(OrderInterface $order, string $transactionId): MatchResult
    {
        if ($this->isAlreadyPaid($order)) {
            return MatchResult::alreadyPaid((string)__('Order is already paid.'));
        }

        $storeId = (int)$order->getStoreId();
        $accountId = $this->config->getAccountId($storeId);
        if ($accountId === '') {
            return MatchResult::error((string)__('No Sham Cash account is configured.'));
        }

        try {
            $transactions = $this->apiClient->transactions(
                $accountId,
                [
                    'start_at' => $this->windowStart($order, $storeId),
                    'end_at' => $this->now(),
                    'coin_id' => $this->config->getCoinId((string)$order->getOrderCurrencyCode(), $storeId),
                    'limit' => self::DEFAULT_LIMIT,
                ],
                $storeId
            );
        } catch (SubscriptionUnavailableException $e) {
            return MatchResult::subscriptionUnavailable($e->getMessage());
        } catch (ApiException $e) {
            return MatchResult::error($e->getMessage());
        }

        foreach ($transactions as $transaction) {
            if ($transaction->getTransactionId() !== $transactionId) {
                continue;
            }
            if ($this->isClaimedByOther($transaction, $order)) {
                return MatchResult::pending((string)__('That transfer is already claimed by another order.'));
            }
            return $this->claimAndInvoice($order, $transaction, 'manual');
        }

        return MatchResult::error((string)__('Transaction %1 was not found in recent Sham Cash activity.', $transactionId));
    }

    /**
     * Recent transactions for an order's account/currency window, for the admin
     * manual-match UI.
     *
     * @param OrderInterface&Order $order
     * @return Transaction[]
     * @throws ApiException
     */
    public function recentTransactions(OrderInterface $order): array
    {
        $storeId = (int)$order->getStoreId();
        $accountId = $this->config->getAccountId($storeId);
        if ($accountId === '') {
            return [];
        }
        return $this->apiClient->transactions(
            $accountId,
            [
                'start_at' => $this->windowStart($order, $storeId),
                'end_at' => $this->now(),
                'coin_id' => $this->config->getCoinId((string)$order->getOrderCurrencyCode(), $storeId),
                'limit' => self::DEFAULT_LIMIT,
            ],
            $storeId
        );
    }

    /**
     * Pick the transaction that pays this order, honoring the matching mode.
     *
     * @param Transaction[] $transactions
     * @return array{0:Transaction,1:string}|null [transaction, matchedBy]
     */
    private function selectCandidate(
        OrderInterface $order,
        array $transactions,
        string $currency,
        string $expectedAmount,
        int $storeId
    ): ?array {
        $mode = $this->config->getMatchingMode($storeId);
        $tolerance = $this->config->getAmountTolerance($storeId);
        $references = $this->rules->referenceCandidates((string)$order->getIncrementId());

        // Primary: match by note/reference (verified by amount + currency).
        if ($mode === Config::MATCH_MODE_NOTE || $mode === Config::MATCH_MODE_BOTH) {
            foreach ($transactions as $transaction) {
                if (!$this->rules->noteMatches($transaction->getNote(), $references)) {
                    continue;
                }
                if (!$this->rules->currencyMatches($transaction->getCurrencyCode(), $currency)) {
                    continue;
                }
                if (!$this->rules->amountWithinTolerance($transaction->getAmount(), $expectedAmount, $tolerance)) {
                    continue;
                }
                if ($this->isClaimedByOther($transaction, $order)) {
                    continue;
                }
                return [$transaction, Config::MATCH_MODE_NOTE];
            }
        }

        // Fallback: unambiguous amount + currency match within the window.
        if ($mode === Config::MATCH_MODE_AMOUNT || $mode === Config::MATCH_MODE_BOTH) {
            $matches = [];
            foreach ($transactions as $transaction) {
                if (!$this->rules->currencyMatches($transaction->getCurrencyCode(), $currency)) {
                    continue;
                }
                if (!$this->rules->amountWithinTolerance($transaction->getAmount(), $expectedAmount, $tolerance)) {
                    continue;
                }
                if ($this->isClaimedByOther($transaction, $order)) {
                    continue;
                }
                $matches[] = $transaction;
            }
            // Only auto-confirm when exactly one unclaimed transfer fits, to
            // avoid crediting the wrong order when amounts collide.
            if (count($matches) === 1) {
                return [$matches[0], Config::MATCH_MODE_AMOUNT];
            }
        }

        return null;
    }

    /**
     * Claim the transaction (unique-index guarded) and invoice the order.
     *
     * @param OrderInterface&Order $order
     */
    private function claimAndInvoice(OrderInterface $order, Transaction $transaction, string $matchedBy): MatchResult
    {
        $orderId = (int)$order->getId();
        $claimed = $this->matchResource->claim(
            $transaction->getTransactionId(),
            $orderId,
            (string)$order->getIncrementId(),
            $this->rules->normalize($transaction->getAmount()),
            $transaction->getCurrencyCode(),
            $matchedBy
        );

        if (!$claimed) {
            // Lost the race or already recorded for this order.
            if ($this->matchResource->getOrderIdByTransactionId($transaction->getTransactionId()) === $orderId) {
                return MatchResult::alreadyPaid((string)__('Payment already recorded for this order.'));
            }
            return MatchResult::pending((string)__('Matching transfer was already claimed by another order.'));
        }

        try {
            $this->paymentProcessor->process($order, $transaction, $matchedBy);
        } catch (\Throwable $e) {
            // Roll back the claim so the order can be retried next run.
            $this->logger->error('[ShamCash] Failed to invoice order ' . $orderId . ': ' . $e->getMessage());
            $this->releaseClaim($transaction->getTransactionId());
            return MatchResult::error((string)__('Matched a transfer but could not invoice the order: %1', $e->getMessage()));
        }

        return MatchResult::matched(
            $transaction->getTransactionId(),
            (string)__('Payment confirmed via Sham Cash transaction %1.', $transaction->getTransactionId())
        );
    }

    private function releaseClaim(string $transactionId): void
    {
        try {
            $connection = $this->matchResource->getConnection();
            $connection->delete($this->matchResource->getMainTable(), ['transaction_id = ?' => $transactionId]);
        } catch (\Throwable $e) {
            $this->logger->error('[ShamCash] Could not release claim ' . $transactionId . ': ' . $e->getMessage());
        }
    }

    private function isClaimedByOther(Transaction $transaction, OrderInterface $order): bool
    {
        $claimedBy = $this->matchResource->getOrderIdByTransactionId($transaction->getTransactionId());
        return $claimedBy !== null && $claimedBy !== (int)$order->getId();
    }

    private function isAlreadyPaid(OrderInterface $order): bool
    {
        $state = (string)$order->getState();
        if (in_array($state, [Order::STATE_PROCESSING, Order::STATE_COMPLETE, Order::STATE_CLOSED], true)) {
            return true;
        }
        return $order->getId() !== null && !$order->canInvoice() && (float)$order->getTotalDue() <= 0.0;
    }

    private function windowStart(OrderInterface $order, int $storeId): string
    {
        $created = (string)$order->getCreatedAt();
        $base = $created !== ''
            ? new \DateTimeImmutable($created, new \DateTimeZone('UTC'))
            : new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $grace = $this->config->getTimeWindowGraceMinutes($storeId);
        if ($grace > 0) {
            $base = $base->sub(new \DateInterval('PT' . $grace . 'M'));
        }
        return $base->format(\DateTimeInterface::ATOM);
    }

    private function now(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM);
    }
}
