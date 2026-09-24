<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuctionRegistration;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Persetujuan pendaftaran sesi (uang jaminan). */
class RegistrationController extends Controller
{
    public function decide(Request $request, AuctionRegistration $registration): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $registration->status = RegistrationStatus::from($data['decision']);
        $registration->note = $data['note'] ?? null;
        $registration->save();

        AuditLogger::log('registration.'.$data['decision'], $registration);

        return back()->with('success', 'Pendaftaran peserta diperbarui.');
    }

    public function proof(AuctionRegistration $registration): StreamedResponse
    {
        abort_unless($registration->deposit_proof && Storage::disk('local')->exists($registration->deposit_proof), 404);

        return Storage::disk('local')->response($registration->deposit_proof, headers: ['Cache-Control' => 'private, no-store']);
    }
}
