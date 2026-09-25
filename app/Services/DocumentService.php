<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/** Pembuatan dokumen PDF (invoice & berita acara serah terima). */
class DocumentService
{
    public function invoice(Invoice $invoice): Response
    {
        $invoice->loadMissing('user', 'lot.item.category', 'lot.auction');

        return Pdf::loadView('pdf.invoice', $this->common() + ['invoice' => $invoice])
            ->setPaper('a4')
            ->download("{$invoice->number}.pdf");
    }

    public function handover(Invoice $invoice): Response
    {
        $invoice->loadMissing('user', 'lot.item.category', 'lot.item.consignor', 'lot.auction');

        return Pdf::loadView('pdf.handover', $this->common() + ['invoice' => $invoice])
            ->setPaper('a4')
            ->download("BAST-{$invoice->number}.pdf");
    }

    private function common(): array
    {
        return [
            'company' => config('app.name'),
            'bank' => config('auction.bank'),
            'rupiah' => fn (int $v) => 'Rp '.number_format($v, 0, ',', '.'),
            'date' => fn ($d) => $d?->timezone(config('app.timezone'))->translatedFormat('d F Y H:i') ?? '-',
        ];
    }
}
