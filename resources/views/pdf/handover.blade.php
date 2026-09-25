<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara Serah Terima {{ $invoice->number }}</title>
    @include('pdf._style')
</head>
<body>
    <div class="head">
        <div class="brand">{{ $company }}</div>
        <div class="muted">Lelang online barang titipan</div>
    </div>

    <h1 style="text-align:center">BERITA ACARA SERAH TERIMA BARANG</h1>
    <p style="text-align:center" class="muted">Nomor: BAST-{{ $invoice->number }}</p>

    <p>Pada {{ $date($invoice->delivered_at ?? now()) }}, telah dilakukan serah terima barang hasil lelang dengan rincian:</p>

    <table class="items">
        <tbody>
            <tr><td style="width:35%" class="muted">Barang</td><td><strong>{{ $invoice->lot->item->title }}</strong></td></tr>
            <tr><td class="muted">Kode barang</td><td>{{ $invoice->lot->item->code }}</td></tr>
            <tr><td class="muted">Kategori</td><td>{{ $invoice->lot->item->category?->name }}</td></tr>
            @foreach (($invoice->lot->item->category?->attribute_schema ?? []) as $field)
                @if (filled($invoice->lot->item->specs[$field['key']] ?? null))
                    <tr><td class="muted">{{ $field['label'] }}</td><td>{{ $invoice->lot->item->specs[$field['key']] }}</td></tr>
                @endif
            @endforeach
            <tr><td class="muted">Sesi / lot</td><td>{{ $invoice->lot->auction->title }} · Lot {{ $invoice->lot->lot_number }}</td></tr>
            <tr><td class="muted">Invoice</td><td>{{ $invoice->number }} — LUNAS {{ $date($invoice->paid_at) }}</td></tr>
            <tr><td class="muted">Harga palu</td><td>{{ $rupiah($invoice->hammer_price) }}</td></tr>
        </tbody>
    </table>

    <p style="margin-top:16px">
        Pihak penerima menyatakan telah menerima barang tersebut dalam kondisi sesuai deskripsi dan hasil pemeriksaan
        yang ditampilkan pada saat lelang. Dengan ditandatanganinya berita acara ini, tanggung jawab atas barang
        beralih sepenuhnya kepada pihak penerima.
    </p>

    <table class="sign">
        <tr>
            <td>Yang menyerahkan,<br>{{ $company }}<br><br><br><br>(................................)</td>
            <td>Yang menerima,<br>Pemenang lelang<br><br><br><br>({{ $invoice->user->name }})</td>
        </tr>
    </table>

    <div class="footer">{{ $company }} · BAST-{{ $invoice->number }}</div>
</body>
</html>
