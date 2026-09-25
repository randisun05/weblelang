<?php

namespace App\Notifications;

use App\Models\Lot;

class OutbidNotification extends AuctionNotification
{
    public function __construct(public Lot $lot, public int $price)
    {
        parent::__construct();
    }

    protected function icon(): string
    {
        return '⚠️';
    }

    protected function title(): string
    {
        return 'Penawaran Anda terlampaui';
    }

    protected function body(): string
    {
        return "Penawaran Anda untuk \"{$this->lot->item->title}\" (Lot {$this->lot->lot_number}) dilampaui. "
            .'Penawaran tertinggi sekarang Rp '.number_format($this->price, 0, ',', '.').'.';
    }

    protected function url(): string
    {
        return route('lots.show', $this->lot);
    }

    protected function actionText(): string
    {
        return 'Tawar lagi';
    }
}
