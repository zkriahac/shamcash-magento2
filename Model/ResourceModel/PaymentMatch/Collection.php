<?php
/**
 * Collection for shamcash_payment_match.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Model\ResourceModel\PaymentMatch;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use ShamCash\Payment\Model\PaymentMatch;
use ShamCash\Payment\Model\ResourceModel\PaymentMatch as PaymentMatchResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(PaymentMatch::class, PaymentMatchResource::class);
    }
}
