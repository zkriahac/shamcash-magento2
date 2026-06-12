<?php
/**
 * Data object for payment instructions.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Model;

use Magento\Framework\DataObject;
use ShamCash\Payment\Api\Data\PaymentInstructionsInterface;

class PaymentInstructionsResult extends DataObject implements PaymentInstructionsInterface
{
    public function getReference(): string
    {
        return (string)$this->getData(self::REFERENCE);
    }

    public function setReference(string $reference): PaymentInstructionsInterface
    {
        return $this->setData(self::REFERENCE, $reference);
    }

    public function getAmount(): string
    {
        return (string)$this->getData(self::AMOUNT);
    }

    public function setAmount(string $amount): PaymentInstructionsInterface
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    public function getCurrency(): string
    {
        return (string)$this->getData(self::CURRENCY);
    }

    public function setCurrency(string $currency): PaymentInstructionsInterface
    {
        return $this->setData(self::CURRENCY, $currency);
    }

    public function getWalletAddress(): string
    {
        return (string)$this->getData(self::WALLET_ADDRESS);
    }

    public function setWalletAddress(string $walletAddress): PaymentInstructionsInterface
    {
        return $this->setData(self::WALLET_ADDRESS, $walletAddress);
    }

    public function getQrPayload(): string
    {
        return (string)$this->getData(self::QR_PAYLOAD);
    }

    public function setQrPayload(string $qrPayload): PaymentInstructionsInterface
    {
        return $this->setData(self::QR_PAYLOAD, $qrPayload);
    }

    public function getStatus(): string
    {
        return (string)$this->getData(self::STATUS);
    }

    public function setStatus(string $status): PaymentInstructionsInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getInstructions(): string
    {
        return (string)$this->getData(self::INSTRUCTIONS);
    }

    public function setInstructions(string $instructions): PaymentInstructionsInterface
    {
        return $this->setData(self::INSTRUCTIONS, $instructions);
    }
}
