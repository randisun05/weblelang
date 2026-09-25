<?php

namespace App\Notifications;

use App\Models\Lot;

class LotEndingSoonNotification extends AuctionNotification
{
    public function __construct(public Lot $lot)
    {
        parent::__construct();
    }

    protected function icon(): string
    {
        return '⏱';
    }

    protected function title(): string
    {
        return 'Lot segera berakhir';
    }

    protected function body(): string
    {
        return "\"{$this->lot->item->title}\" (Lot {$this->lot->lot_number}) berakhir pukul "
            .$this->lot->ends_at->timezone(config('app.timezone'))->format('H:i').'. Jangan sampai terlewat!';
    }

    protected function url(): string
    {
        return route('lots.show', $this->lot);
    }
}
