<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use App\Services\Auction\BidException;
use App\Services\Auction\BidService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BidController extends Controller
{
    public function buyNow(Request $request, Lot $lot, BidService $bids): RedirectResponse
    {
        try {
            $lot = $bids->buyNow($lot, $request->user(), ['ip' => $request->ip(), 'user_agent' => $request->userAgent()]);
        } catch (BidException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('user.invoices.show', $lot->invoice)
            ->with('success', 'Selamat! Barang berhasil Anda beli langsung. Silakan selesaikan pembayaran.');
    }

    public function store(Request $request, Lot $lot, BidService $bids): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'max_amount' => ['nullable', 'integer', 'min:1', 'max:999999999999'],
        ]);

        try {
            $bids->place($lot, $request->user(), (int) $data['amount'], isset($data['max_amount']) ? (int) $data['max_amount'] : null, [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (BidException $e) {
            return back()->with('error', $e->getMessage());
        }

        $lot->refresh();

        if ($lot->isConcealed()) {
            return back()->with('success', 'Penawaran tertutup Anda tercatat: Rp '.number_format((int) $data['amount'], 0, ',', '.')
                .'. Anda masih dapat mengubahnya sebelum lot ditutup.');
        }

        return $lot->leader_id === $request->user()->id
            ? back()->with('success', 'Penawaran diterima. Anda penawar tertinggi saat ini!')
            : back()->with('warning', 'Penawaran tercatat, namun langsung dilampaui auto-bid peserta lain. Coba tawar lebih tinggi.');
    }
}
