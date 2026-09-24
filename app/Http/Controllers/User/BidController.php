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

        return $lot->leader_id === $request->user()->id
            ? back()->with('success', 'Penawaran diterima. Anda penawar tertinggi saat ini!')
            : back()->with('warning', 'Penawaran tercatat, namun langsung dilampaui auto-bid peserta lain. Coba tawar lebih tinggi.');
    }
}
