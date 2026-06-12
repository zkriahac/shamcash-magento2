<?php
/**
 * Resource model for shamcash_payment_match.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Model\ResourceModel;

use Magento\Framework\DB\Adapter\DuplicateException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class PaymentMatch extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('shamcash_payment_match', 'match_id');
    }

    /**
     * Atomically claim a Sham Cash transaction for an order. Relies on the
     * unique index on transaction_id: only the first caller wins, so a transfer
     * can never be credited to two orders.
     *
     * @return bool True if this call claimed the transaction, false if it was
     *              already claimed (by any order).
     */
    public function claim(
        string $transactionId,
        int $orderId,
        ?string $orderIncrementId,
        ?string $amount,
        ?string $currency,
        string $matchedBy
    ): bool {
        $connection = $this->getConnection();
        try {
            $connection->insert($this->getMainTable(), [
                'transaction_id' => $transactionId,
                'order_id' => $orderId,
                'order_increment_id' => $orderIncrementId,
                'amount' => $amount,
                'currency' => $currency,
                'matched_by' => $matchedBy,
            ]);
            return true;
        } catch (DuplicateException $e) {
            return false;
        }
    }

    /**
     * The order id that already claimed a transaction, or null if unclaimed.
     */
    public function getOrderIdByTransactionId(string $transactionId): ?int
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), ['order_id'])
            ->where('transaction_id = ?', $transactionId)
            ->limit(1);
        $value = $connection->fetchOne($select);
        return $value === false || $value === null ? null : (int)$value;
    }
}
