<?php

namespace App\Http\Controllers\User;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\AuditLogger;
use App\Services\ImageService;
use App\Support\Present;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('User/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'whatsapp_notifications' => $user->whatsapp_notifications,
                'address' => $user->address,
                'nik_masked' => $user->nik ? substr($user->nik, 0, 4).'********'.substr($user->nik, -4) : null,
                'has_ktp' => (bool) $user->ktp_path,
                'kyc' => Present::status($user->kyc_status),
                'kyc_note' => $user->kyc_note,
                'bank_name' => $user->bank_name,
                'bank_holder' => $user->bank_holder,
                'bank_account_masked' => $user->bank_account ? str_repeat('*', max(0, strlen($user->bank_account) - 4)).substr($user->bank_account, -4) : null,
            ],
            'banks' => config('payments.banks'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'address' => ['nullable', 'string', 'max:500'],
            'whatsapp_notifications' => ['boolean'],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Profil diperbarui.');
    }

    /**
     * Hak akses data pribadi (UU PDP): unduh salinan data milik pengguna dalam format JSON.
     * Penawaran pada lot tertutup yang belum ditutup tetap disertakan karena milik pengguna sendiri.
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user()->load([
            'bids' => fn ($q) => $q->latest('id'),
            'invoices',
            'registrations.auction:id,title,code',
            'watchlist:id,lot_number',
        ]);

        AuditLogger::log('user.data_exported', $user);

        $data = [
            'diekspor_pada' => now()->toIso8601String(),
            'profil' => [
                'nama' => $user->name, 'email' => $user->email, 'telepon' => $user->phone, 'alamat' => $user->address,
                'nik' => $user->nik, 'status_kyc' => $user->kyc_status->label(),
                'rekening' => $user->hasBankAccount() ? ['bank' => $user->bank_name, 'nomor' => $user->bank_account, 'atas_nama' => $user->bank_holder] : null,
                'terdaftar' => $user->created_at->toIso8601String(),
                'persetujuan_syarat' => ['versi' => $user->terms_version, 'pada' => $user->terms_accepted_at?->toIso8601String()],
            ],
            'penawaran' => $user->bids->map(fn ($b) => ['lot_id' => $b->lot_id, 'nominal' => $b->amount, 'otomatis' => $b->is_auto, 'waktu' => $b->created_at->toIso8601String(), 'ip' => $b->ip]),
            'invoice' => $user->invoices->map(fn ($i) => ['nomor' => $i->number, 'total' => $i->total, 'status' => $i->status->label(), 'dibayar' => $i->paid_at?->toIso8601String()]),
            'pendaftaran_sesi' => $user->registrations->map(fn ($r) => ['sesi' => $r->auction->title, 'status' => $r->status->label(), 'jaminan' => $r->deposit_status?->label()]),
            'pembayaran' => Payment::where('user_id', $user->id)->get()->map(fn ($p) => ['referensi' => $p->reference, 'gateway' => $p->gateway, 'nominal' => $p->amount, 'status' => $p->status->label(), 'metode' => $p->method]),
            'daftar_pantauan' => $user->watchlist->pluck('id'),
            'notifikasi' => $user->notifications()->limit(500)->get()->map(fn ($n) => ['judul' => $n->data['title'] ?? null, 'isi' => $n->data['body'] ?? null, 'waktu' => $n->created_at->toIso8601String()]),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="data-saya-'.now()->format('Ymd').'.json"',
            'Cache-Control' => 'private, no-store',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Rekening untuk pengembalian uang jaminan. */
    public function updateBank(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bank_name' => ['required', Rule::in(array_keys(config('payments.banks')))],
            'bank_account' => ['required', 'string', 'regex:/^[0-9]{5,20}$/'],
            'bank_holder' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update($data);
        AuditLogger::log('user.bank_updated', $request->user());

        return back()->with('success', 'Rekening pengembalian jaminan disimpan.');
    }

    /** Pengajuan verifikasi identitas. KTP disimpan di disk privat. */
    public function submitKyc(Request $request, ImageService $images): RedirectResponse
    {
        $user = $request->user();

        if ($user->kyc_status === KycStatus::Verified) {
            return back()->with('info', 'Identitas Anda sudah terverifikasi.');
        }

        $data = $request->validate([
            'nik' => ['required', 'digits:16'],
            'address' => ['required', 'string', 'max:500'],
            'ktp' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($user->ktp_path) {
            Storage::disk('local')->delete($user->ktp_path);
        }

        $user->forceFill([
            'nik' => $data['nik'],
            'address' => $data['address'],
            'ktp_path' => $images->store($request->file('ktp'), 'kyc', 'local', 1400),
            'kyc_status' => KycStatus::Pending,
            'kyc_note' => null,
        ])->save();

        AuditLogger::log('kyc.submitted', $user);

        return back()->with('success', 'Data identitas terkirim. Admin akan memverifikasi dalam 1x24 jam.');
    }
}
