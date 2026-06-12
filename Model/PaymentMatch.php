<?php
/**
 * Entity for a claimed Sham Cash transaction.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Model;

use Magento\Framework\Model\AbstractModel;
use ShamCash\Payment\Model\ResourceModel\PaymentMatch as PaymentMatchResource;

/**
 * One row of shamcash_payment_match: a transaction_id credited to an order.
 *
 * @method string|null getTransactionId()
 * @method $this setTransactionId(string $value)
 * @method int|null getOrderId()
 * @method $this setOrderId(int $value)
 * @method string|null getOrderIncrementId()
 * @method $this setOrderIncrementId(string $value)
 * @method string|null getAmount()
 * @method $this setAmount(string $value)
 * @method string|null getCurrency()
 * @method $this setCurrency(string $value)
 * @method string|null getMatchedBy()
 * @method $this setMatchedBy(string $value)
 * @method string|null getCreatedAt()
 */
class PaymentMatch extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(PaymentMatchResource::class);
    }
}
