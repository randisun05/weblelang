<?php

namespace App\Http\Controllers\User;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\ImageService;
use App\Support\Present;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
                'address' => $user->address,
                'nik_masked' => $user->nik ? substr($user->nik, 0, 4).'********'.substr($user->nik, -4) : null,
                'has_ktp' => (bool) $user->ktp_path,
                'kyc' => Present::status($user->kyc_status),
                'kyc_note' => $user->kyc_note,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(\+62|62|0)8[0-9]{7,12}$/'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Profil diperbarui.');
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
