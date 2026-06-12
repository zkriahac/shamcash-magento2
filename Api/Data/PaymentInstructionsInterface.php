<?php
/**
 * Customer-facing Sham Cash payment instructions for an order.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Api\Data;

/**
 * Everything the storefront needs to render "pay the merchant" instructions:
 * the wallet to send to, the QR payload, the amount/currency, and the order
 * reference to include in the transfer note.
 *
 * @api
 */
interface PaymentInstructionsInterface
{
    public const REFERENCE = 'reference';
    public const AMOUNT = 'amount';
    public const CURRENCY = 'currency';
    public const WALLET_ADDRESS = 'wallet_address';
    public const QR_PAYLOAD = 'qr_payload';
    public const STATUS = 'status';
    public const INSTRUCTIONS = 'instructions';

    public function getReference(): string;

    public function setReference(string $reference): self;

    public function getAmount(): string;

    public function setAmount(string $amount): self;

    public function getCurrency(): string;

    public function setCurrency(string $currency): self;

    public function getWalletAddress(): string;

    public function setWalletAddress(string $walletAddress): self;

    public function getQrPayload(): string;

    public function setQrPayload(string $qrPayload): self;

    /**
     * Payment status of the order: pending or paid.
     */
    public function getStatus(): string;

    public function setStatus(string $status): self;

    public function getInstructions(): string;

    public function setInstructions(string $instructions): self;
}
