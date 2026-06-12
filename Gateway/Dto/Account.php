<?php
/**
 * Immutable representation of a linked Sham Cash account.
 */
declare(strict_types=1);

namespace ShamCash\Payment\Gateway\Dto;

/**
 * Mirrors the /accounts object: id, name, email, phone, status,
 * subscription_expires_at, address, qr_payload.
 */
class Account
{
    public const STATUS_ACTIVE = 'active';

    public function __construct(
        private readonly string $id,
        private readonly ?string $name,
        private readonly ?string $email,
        private readonly ?string $phone,
        private readonly string $status,
        private readonly ?string $subscriptionExpiresAt,
        private readonly ?string $address,
        private readonly ?string $qrPayload
    ) {
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (string)($row['id'] ?? ''),
            isset($row['name']) ? (string)$row['name'] : null,
            isset($row['email']) ? (string)$row['email'] : null,
            isset($row['phone']) ? (string)$row['phone'] : null,
            (string)($row['status'] ?? 'inactive'),
            isset($row['subscription_expires_at']) ? (string)$row['subscription_expires_at'] : null,
            isset($row['address']) ? (string)$row['address'] : null,
            isset($row['qr_payload']) ? (string)$row['qr_payload'] : null
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return strtolower($this->status) === self::STATUS_ACTIVE;
    }

    public function getSubscriptionExpiresAt(): ?string
    {
        return $this->subscriptionExpiresAt;
    }

    /**
     * Wallet address the merchant receives transfers on.
     */
    public function getAddress(): ?string
    {
        return $this->address;
    }

    /**
     * QR code payload customers scan to send a transfer.
     */
    public function getQrPayload(): ?string
    {
        return $this->qrPayload;
    }
}
