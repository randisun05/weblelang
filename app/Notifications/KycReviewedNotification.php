<?php

namespace App\Notifications;

use App\Enums\KycStatus;
use App\Models\User;

class KycReviewedNotification extends AuctionNotification
{
    public function __construct(public User $user)
    {
        parent::__construct();
    }

    private function verified(): bool
    {
        return $this->user->kyc_status === KycStatus::Verified;
    }

    protected function icon(): string
    {
        return $this->verified() ? '🪪' : '⚠️';
    }

    protected function title(): string
    {
        return $this->verified() ? 'Identitas Anda terverifikasi' : 'Verifikasi identitas ditolak';
    }

    protected function body(): string
    {
        return $this->verified()
            ? 'Akun Anda sudah terverifikasi. Sekarang Anda dapat menawar di semua lelang.'
            : 'Pengajuan KYC ditolak: '.$this->user->kyc_note.'. Silakan perbaiki dan kirim ulang.';
    }

    protected function url(): string
    {
        return $this->verified() ? route('auctions.index') : route('user.profile');
    }
}
