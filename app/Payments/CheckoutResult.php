<?php

namespace App\Payments;

final readonly class CheckoutResult
{
    public function __construct(
        public string $url,
        public ?string $providerRef = null,
    ) {}
}
