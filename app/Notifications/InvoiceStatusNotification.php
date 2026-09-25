<?php

namespace App\Notifications;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

/** Invoice lunas atau dibatalkan. */
class InvoiceStatusNotification extends AuctionNotification
{
    public function __construct(public Invoice $invoice)
    {
        parent::__construct();
    }

    private function paid(): bool
    {
        return $this->invoice->status === InvoiceStatus::Paid;
    }

    protected function icon(): string
    {
        return $this->paid() ? '✅' : '❌';
    }

    protected function title(): string
    {
        return $this->paid() ? "Pembayaran {$this->invoice->number} diterima" : "Invoice {$this->invoice->number} dibatalkan";
    }

    protected function body(): string
    {
        return $this->paid()
            ? 'Terima kasih, pembayaran Anda sudah kami terima. Tim kami akan menghubungi Anda untuk serah terima barang.'
            : 'Invoice dibatalkan: '.($this->invoice->cancel_reason ?? 'tidak dibayar sebelum jatuh tempo').'.';
    }

    protected function url(): string
    {
        return route('user.invoices.show', $this->invoice);
    }
}
