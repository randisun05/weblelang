<?php

namespace App\Exports;

use App\Models\Settlement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Laporan settlement ke penitip — dipakai juga sebagai daftar transfer bank. */
class SettlementsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithTitle
{
    public function __construct(private Carbon $from, private Carbon $to) {}

    public function query(): Builder
    {
        return Settlement::query()
            ->with('consignor', 'invoice.lot.item:id,code,title')
            ->whereBetween('created_at', [$this->from, $this->to])
            ->orderBy('id');
    }

    public function headings(): array
    {
        return ['No. Settlement', 'Tanggal', 'Kode Penitip', 'Penitip', 'Bank', 'No. Rekening', 'Atas Nama',
            'Kode Barang', 'Barang', 'Invoice', 'Harga Palu', 'Komisi (%)', 'Komisi', 'Biaya Lain', 'Diterima Penitip', 'Status', 'Ditransfer'];
    }

    /** @param  Settlement  $settlement */
    public function map(mixed $settlement): array
    {
        return [
            $settlement->number,
            $settlement->created_at->format('Y-m-d H:i'),
            $settlement->consignor->code,
            $settlement->consignor->name,
            $settlement->consignor->bank_name,
            // Awali tanda kutip supaya Excel tidak mengubah nomor rekening menjadi notasi ilmiah.
            $settlement->consignor->bank_account ? "'".$settlement->consignor->bank_account : null,
            $settlement->consignor->bank_holder,
            $settlement->invoice->lot->item->code,
            $settlement->invoice->lot->item->title,
            $settlement->invoice->number,
            $settlement->hammer_price,
            $settlement->commission_rate,
            $settlement->commission,
            $settlement->other_fees,
            $settlement->net_amount,
            $settlement->status->label(),
            $settlement->paid_at?->format('Y-m-d H:i'),
        ];
    }

    public function title(): string
    {
        return 'Settlement';
    }
}
