<?php

namespace App\Exports;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Laporan penjualan: semua invoice pada periode tertentu (berdasarkan tanggal terbit). */
class SalesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private Carbon $from, private Carbon $to) {}

    public function query(): Builder
    {
        return Invoice::query()
            ->with('user:id,name,email', 'lot.auction:id,code,title,method,status', 'lot.item:id,code,title,consignor_id', 'lot.item.consignor:id,name', 'settlement')
            ->whereBetween('created_at', [$this->from, $this->to])
            ->orderBy('id');
    }

    public function headings(): array
    {
        return ['No. Invoice', 'Tanggal', 'Sesi', 'Lot', 'Kode Barang', 'Barang', 'Penitip', 'Pemenang', 'Email',
            'Harga Palu', 'Premi Pembeli', 'Biaya Admin', 'Total', 'Komisi', 'Status', 'Dibayar', 'Metode', 'Diserahkan'];
    }

    /** @param  Invoice  $invoice */
    public function map(mixed $invoice): array
    {
        return [
            $invoice->number,
            $invoice->created_at->format('Y-m-d H:i'),
            $invoice->lot->auction->code.' — '.$invoice->lot->auction->title,
            $invoice->lot->lot_number,
            $invoice->lot->item->code,
            $invoice->lot->item->title,
            $invoice->lot->item->consignor?->name,
            $invoice->user->name,
            $invoice->user->email,
            $invoice->hammer_price,
            $invoice->buyer_premium,
            $invoice->admin_fee,
            $invoice->total,
            $invoice->settlement?->commission,
            $invoice->status->label(),
            $invoice->paid_at?->format('Y-m-d H:i'),
            $invoice->payment_method,
            $invoice->delivered_at?->format('Y-m-d H:i'),
        ];
    }

    public function title(): string
    {
        return 'Penjualan';
    }
}
