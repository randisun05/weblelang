<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DepositStatus;
use App\Enums\RegistrationStatus;
use App\Http\Controllers\Controller;
use App\Models\AuctionRegistration;
use App\Notifications\DepositSettledNotification;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Persetujuan pendaftaran sesi dan pengelolaan uang jaminan. */
class RegistrationController extends Controller
{
    public function decide(Request $request, AuctionRegistration $registration): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ($registration->deposit_status && $registration->deposit_status !== DepositStatus::Held) {
            return back()->with('error', 'Jaminan peserta ini sudah diselesaikan; status pendaftaran tidak dapat diubah.');
        }

        $registration->status = RegistrationStatus::from($data['decision']);
        $registration->note = $data['note'] ?? null;
        // Jaminan dianggap diterima (ditahan) begitu pendaftaran disetujui.
        $registration->deposit_status = $registration->status === RegistrationStatus::Approved ? DepositStatus::Held : null;
        $registration->save();

        AuditLogger::log('registration.'.$data['decision'], $registration);

        return back()->with('success', 'Pendaftaran peserta diperbarui.');
    }

    /** Mengembalikan atau menyita uang jaminan. */
    public function settleDeposit(Request $request, AuctionRegistration $registration): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:refunded,forfeited'],
            'note' => ['required_if:decision,forfeited', 'nullable', 'string', 'max:255'],
        ]);

        if ($registration->deposit_status !== DepositStatus::Held) {
            return back()->with('error', 'Hanya jaminan yang sedang ditahan yang dapat diproses.');
        }

        $registration->forceFill([
            'deposit_status' => DepositStatus::from($data['decision']),
            'deposit_settled_at' => now(),
            'note' => $data['note'] ?? $registration->note,
        ])->save();

        AuditLogger::log('deposit.'.$data['decision'], $registration, ['note' => $data['note'] ?? null]);
        $registration->user->notify(new DepositSettledNotification($registration->load('auction')));

        return back()->with('success', $data['decision'] === 'refunded' ? 'Jaminan ditandai sudah dikembalikan.' : 'Jaminan dinyatakan hangus.');
    }

    public function proof(AuctionRegistration $registration): StreamedResponse
    {
        abort_unless($registration->deposit_proof && Storage::disk('local')->exists($registration->deposit_proof), 404);

        return Storage::disk('local')->response($registration->deposit_proof, headers: ['Cache-Control' => 'private, no-store']);
    }
}
