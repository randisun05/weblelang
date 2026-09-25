<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->number }}</title>
    @include('pdf._style')
</head>
<body>
    <div class="head">
        <table class="grid">
            <tr>
                <td><div class="brand">{{ $company }}</div><div class="muted">Lelang online barang titipan</div></td>
                <td class="right">
                    <h1>INVOICE</h1>
                    <div>{{ $invoice->number }}</div>
                    <span class="badge {{ $invoice->status->value }}">{{ strtoupper($invoice->status->label()) }}</span>
                </td>
            </tr>
        </table>
    </div>

    <table class="grid">
        <tr>
            <td style="width:55%">
                <div class="muted">Ditagihkan kepada</div>
                <strong>{{ $invoice->user->name }}</strong><br>
                {{ $invoice->user->email }}<br>{{ $invoice->user->phone }}
            </td>
            <td>
                <div class="muted">Tanggal terbit</div>{{ $date($invoice->created_at) }}<br>
                <div class="muted" style="margin-top:6px">Jatuh tempo</div>{{ $date($invoice->due_at) }}
                @if ($invoice->paid_at)
                    <div class="muted" style="margin-top:6px">Dibayar</div>{{ $date($invoice->paid_at) }} ({{ $invoice->payment_method }})
                @endif
            </td>
        </tr>
    </table>

    <table class="items" style="margin-top:18px">
        <thead>
            <tr><th>Uraian</th><th class="right">Jumlah</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $invoice->lot->item->title }}</strong><br>
                    <span class="muted">{{ $invoice->lot->auction->title }} · Lot {{ $invoice->lot->lot_number }} · Kode {{ $invoice->lot->item->code }}</span><br>
                    Harga palu (penawaran menang)
                </td>
                <td class="right">{{ $rupiah($invoice->hammer_price) }}</td>
            </tr>
            <tr>
                <td>Premi pembeli ({{ rtrim(rtrim(number_format($invoice->lot->auction->buyer_premium_rate, 2, ',', '.'), '0'), ',') }}%)</td>
                <td class="right">{{ $rupiah($invoice->buyer_premium) }}</td>
            </tr>
            @if ($invoice->admin_fee)
                <tr><td>Biaya administrasi</td><td class="right">{{ $rupiah($invoice->admin_fee) }}</td></tr>
            @endif
            <tr class="total"><td>TOTAL</td><td class="right">{{ $rupiah($invoice->total) }}</td></tr>
        </tbody>
    </table>

    @if ($invoice->status->value === 'unpaid')
        <div class="box">
            <strong>Pembayaran transfer</strong><br>
            {{ $bank['name'] }} {{ $bank['account'] }} a.n. {{ $bank['holder'] }}<br>
            Cantumkan nomor invoice <strong>{{ $invoice->number }}</strong> pada berita transfer,
            lalu unggah bukti transfer melalui akun Anda.
        </div>
    @endif

    <div class="box muted">
        Barang dijual dalam kondisi apa adanya sesuai hasil pemeriksaan. Invoice yang tidak dibayar sebelum jatuh tempo
        dibatalkan otomatis dan uang jaminan (jika ada) dinyatakan hangus.
    </div>

    <div class="footer">{{ $company }} · Dokumen dibuat otomatis pada {{ $date(now()) }}</div>
</body>
</html>
