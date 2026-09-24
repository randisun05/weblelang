<?php

namespace App\Http\Controllers\User;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\Auction;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Pendaftaran sesi + unggah bukti transfer uang jaminan. */
class AuctionRegistrationController extends Controller
{
    public function store(Request $request, Auction $auction, ImageService $images): RedirectResponse
    {
        abort_unless($auction->isPublic() && $auction->requiresRegistration(), 404);

        $request->validate(['proof' => ['required', 'image', 'max:5120']]);

        $registration = $auction->registrations()->firstOrNew(['user_id' => $request->user()->id]);

        if ($registration->exists && $registration->status === RegistrationStatus::Approved) {
            return back()->with('info', 'Anda sudah terdaftar pada sesi ini.');
        }

        $registration->deposit_proof = $images->store($request->file('proof'), 'deposits', 'local', 1400);
        $registration->status = RegistrationStatus::Pending;
        $registration->save();

        return back()->with('success', 'Bukti jaminan terkirim. Tunggu persetujuan admin.');
    }
}
