<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KycStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Manajemen peserta lelang & verifikasi identitas (KYC). */
class BidderController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'kyc' => ['nullable', Rule::enum(KycStatus::class)],
        ]);

        return Inertia::render('Admin/Bidders/Index', [
            'bidders' => User::where('role', Role::Bidder)
                ->withCount('bids')
                ->when($filters['q'] ?? null, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
                ->when($filters['kyc'] ?? null, fn ($q, $k) => $q->where('kyc_status', $k))
                ->orderByRaw("case kyc_status when 'pending' then 0 else 1 end")
                ->latest()->paginate(20)->withQueryString()
                ->through(fn (User $u) => [
                    'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'phone' => $u->phone,
                    'bids_count' => $u->bids_count, 'is_blocked' => $u->is_blocked,
                    'kyc' => Present::status($u->kyc_status), 'created_at' => $u->created_at->toIso8601String(),
                ]),
            'filters' => $filters,
            'kycStatuses' => KycStatus::options(),
        ]);
    }

    public function show(User $user): Response
    {
        abort_unless($user->role === Role::Bidder, 404);
        $user->load(['invoices' => fn ($q) => $q->latest()->with('lot.item:id,title')]);

        AuditLogger::log('bidder.viewed', $user);

        return Inertia::render('Admin/Bidders/Show', [
            'bidder' => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
                'address' => $user->address, 'nik' => $user->nik, 'has_ktp' => (bool) $user->ktp_path,
                'kyc' => Present::status($user->kyc_status), 'kyc_note' => $user->kyc_note,
                'kyc_verified_at' => $user->kyc_verified_at?->toIso8601String(),
                'is_blocked' => $user->is_blocked, 'created_at' => $user->created_at->toIso8601String(),
            ],
            'invoices' => $user->invoices->map(fn ($inv) => [
                'id' => $inv->id, 'number' => $inv->number, 'title' => $inv->lot->item->title,
                'total' => $inv->total, 'status' => Present::status($inv->status),
            ]),
            'bids' => $user->bids()->with('lot.item:id,title')->latest('id')->limit(20)->get()->map(fn ($b) => [
                'id' => $b->id, 'lot_id' => $b->lot_id, 'title' => $b->lot->item->title, 'amount' => $b->amount,
                'is_auto' => $b->is_auto, 'ip' => $b->ip, 'at' => $b->created_at->toIso8601String(),
            ]),
        ]);
    }

    public function kyc(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === Role::Bidder, 404);

        $data = $request->validate([
            'decision' => ['required', 'in:verified,rejected'],
            'note' => ['required_if:decision,rejected', 'nullable', 'string', 'max:255'],
        ]);

        if ($user->kyc_status !== KycStatus::Pending) {
            return back()->with('error', 'Pengajuan KYC ini sudah diproses.');
        }

        $user->forceFill([
            'kyc_status' => KycStatus::from($data['decision']),
            'kyc_note' => $data['note'] ?? null,
            'kyc_verified_at' => $data['decision'] === 'verified' ? now() : null,
        ])->save();

        AuditLogger::log('kyc.'.$data['decision'], $user, ['note' => $data['note'] ?? null]);

        return back()->with('success', $data['decision'] === 'verified' ? 'Peserta terverifikasi.' : 'Pengajuan KYC ditolak.');
    }

    public function block(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->role === Role::Bidder, 404);

        $user->forceFill(['is_blocked' => ! $user->is_blocked])->save();
        AuditLogger::log($user->is_blocked ? 'bidder.blocked' : 'bidder.unblocked', $user);

        return back()->with('success', $user->is_blocked ? 'Peserta diblokir.' : 'Blokir peserta dibuka.');
    }

    /** KTP disajikan dari disk privat hanya untuk petugas. */
    public function ktp(User $user): StreamedResponse
    {
        abort_unless($user->ktp_path && Storage::disk('local')->exists($user->ktp_path), 404);
        AuditLogger::log('kyc.document_viewed', $user);

        return Storage::disk('local')->response($user->ktp_path, headers: ['Cache-Control' => 'private, no-store']);
    }
}
