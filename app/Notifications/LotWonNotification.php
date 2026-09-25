<?php

namespace App\Notifications;

use App\Models\Invoice;

class LotWonNotification extends AuctionNotification
{
    public function __construct(public Invoice $invoice)
    {
        parent::__construct();
    }

    protected function icon(): string
    {
        return '🏆';
    }

    protected function title(): string
    {
        return 'Selamat, Anda memenangkan lelang!';
    }

    protected function body(): string
    {
        return "Anda memenangkan \"{$this->invoice->lot->item->title}\". Total tagihan Rp "
            .number_format($this->invoice->total, 0, ',', '.').', bayar sebelum '
            .$this->invoice->due_at->timezone(config('app.timezone'))->format('d-m-Y H:i').'.';
    }

    protected function url(): string
    {
        return route('user.invoices.show', $this->invoice);
    }

    protected function actionText(): string
    {
        return 'Bayar sekarang';
    }
}
