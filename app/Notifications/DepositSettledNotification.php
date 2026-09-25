<?php

namespace App\Notifications;

use App\Enums\DepositStatus;
use App\Models\AuctionRegistration;

class DepositSettledNotification extends AuctionNotification
{
    public function __construct(public AuctionRegistration $registration)
    {
        parent::__construct();
    }

    protected function icon(): string
    {
        return $this->registration->deposit_status === DepositStatus::Refunded ? '💸' : '⚠️';
    }

    protected function title(): string
    {
        return $this->registration->deposit_status->label();
    }

    protected function body(): string
    {
        $auction = $this->registration->auction->title;

        return $this->registration->deposit_status === DepositStatus::Refunded
            ? "Uang jaminan sesi \"{$auction}\" telah dikembalikan ke rekening Anda."
            : "Uang jaminan sesi \"{$auction}\" dinyatakan hangus. ".($this->registration->note ?? '');
    }

    protected function url(): string
    {
        return route('auctions.show', $this->registration->auction->slug);
    }
}
