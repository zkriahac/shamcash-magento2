<?php
/**
 * Data object for a reconciliation result.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Model;

use Magento\Framework\DataObject;
use ShamCash\Payment\Api\Data\ReconciliationResultInterface;

class ReconciliationResult extends DataObject implements ReconciliationResultInterface
{
    public function getStatus(): string
    {
        return (string)$this->getData(self::STATUS);
    }

    public function setStatus(string $status): ReconciliationResultInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getMessage(): string
    {
        return (string)$this->getData(self::MESSAGE);
    }

    public function setMessage(string $message): ReconciliationResultInterface
    {
        return $this->setData(self::MESSAGE, $message);
    }

    public function getTransactionId(): ?string
    {
        $value = $this->getData(self::TRANSACTION_ID);
        return $value === null ? null : (string)$value;
    }

    public function setTransactionId(?string $transactionId): ReconciliationResultInterface
    {
        return $this->setData(self::TRANSACTION_ID, $transactionId);
    }
}
