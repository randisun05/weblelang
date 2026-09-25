<?php

namespace App\Payments;

/**
 * Hasil normalisasi webhook / respons gateway, apa pun drivernya.
 * `status` untuk pembayaran: pending|paid|failed|expired; untuk payout: processing|completed|failed.
 */
final readonly class GatewayEvent
{
    public function __construct(
        public string $reference,
        public string $status,
        public ?int $amount = null,
        public ?string $providerRef = null,
        public ?string $method = null,
        public ?string $failureReason = null,
        public array $raw = [],
    ) {}
}
