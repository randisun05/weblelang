<?php

namespace App\Services\Auction;

use App\Enums\LotStatus;
use App\Enums\RegistrationStatus;
use App\Events\BidPlaced;
use App\Models\AutoBid;
use App\Models\Bid;
use App\Models\Lot;
use App\Models\User;
use App\Notifications\OutbidNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Mesin penawaran. Semua perubahan pada lot dilakukan di dalam transaksi
 * dengan row lock (SELECT ... FOR UPDATE) sehingga dua bid yang masuk
 * bersamaan diproses berurutan dan tidak saling menimpa.
 */
class BidService
{
    public function __construct(private BidIncrement $increments) {}

    /**
     * @param  int|null  $maxAmount  Batas auto-bid (proxy). Null = bid manual biasa.
     * @param  array{ip?: string|null, user_agent?: string|null}  $meta
     */
    public function place(Lot $lot, User $user, int $amount, ?int $maxAmount = null, array $meta = []): Lot
    {
        return DB::transaction(function () use ($lot, $user, $amount, $maxAmount, $meta) {
            /** @var Lot $lot */
            $lot = Lot::query()->whereKey($lot->getKey())->lockForUpdate()->firstOrFail();
            $lot->load('auction', 'item.consignor');
            $now = Carbon::now();

            $this->assertCanBid($lot, $user, $now);
            $previousLeaderId = $lot->leader_id;

            if ($maxAmount !== null && $maxAmount < $amount) {
                throw new BidException('Batas auto-bid tidak boleh lebih kecil dari nominal penawaran.');
            }

            // Pemimpin saat ini hanya boleh menaikkan batas auto-bid-nya.
            if ($lot->leader_id === $user->id) {
                if ($maxAmount === null || $maxAmount <= $lot->current_price) {
                    throw new BidException('Anda sudah menjadi penawar tertinggi.');
                }

                $this->upsertAutoBid($lot, $user, $maxAmount);
                $this->resolveProxies($lot, $user, $maxAmount, $now, $meta);

                return $this->finish($lot, $now, $previousLeaderId);
            }

            $minimum = $this->increments->minimumNextBid($lot);

            if ($amount < $minimum) {
                throw new BidException('Penawaran minimum saat ini Rp '.number_format($minimum, 0, ',', '.').'.');
            }

            if ($amount > $minimum * (int) config('auction.max_jump_multiplier', 10)) {
                throw new BidException('Nominal terlalu jauh di atas harga saat ini. Periksa kembali angka yang Anda ketik.');
            }

            if ($maxAmount !== null) {
                $this->upsertAutoBid($lot, $user, $maxAmount);
            }

            $this->record($lot, $user->id, $amount, false, $now, $meta);

            $userMax = max($amount, (int) AutoBid::where('lot_id', $lot->id)
                ->where('user_id', $user->id)->where('is_active', true)->value('max_amount'));

            $this->resolveProxies($lot, $user, $userMax, $now, $meta);

            return $this->finish($lot, $now, $previousLeaderId);
        });
    }

    /** Memeriksa semua syarat sebelum bid diterima. Pesan aman untuk ditampilkan. */
    public function assertCanBid(Lot $lot, User $user, Carbon $now): void
    {
        if ($user->is_blocked) {
            throw new BidException('Akun Anda sedang diblokir. Hubungi admin.');
        }

        if ($user->isBackoffice()) {
            throw new BidException('Akun petugas tidak dapat mengikuti penawaran.');
        }

        if (config('auction.require_kyc') && ! $user->isKycVerified()) {
            throw new BidException('Lengkapi dan tunggu verifikasi identitas (KYC) sebelum menawar.');
        }

        if ($lot->status !== LotStatus::Live || $now->lt($lot->starts_at) || $now->gte($lot->ends_at)) {
            throw new BidException('Lot ini sedang tidak menerima penawaran.');
        }

        $consignorEmail = $lot->item?->consignor?->email;
        if ($consignorEmail && strcasecmp($consignorEmail, $user->email) === 0) {
            throw new BidException('Penitip tidak diperkenankan menawar barangnya sendiri.');
        }

        if ($lot->auction->requiresRegistration()) {
            $approved = $lot->auction->registrations()
                ->where('user_id', $user->id)
                ->where('status', RegistrationStatus::Approved)
                ->exists();

            if (! $approved) {
                throw new BidException('Sesi ini mensyaratkan uang jaminan. Daftar dan tunggu persetujuan admin.');
            }
        }
    }

    private function upsertAutoBid(Lot $lot, User $user, int $maxAmount): void
    {
        AutoBid::updateOrCreate(
            ['lot_id' => $lot->id, 'user_id' => $user->id],
            ['max_amount' => $maxAmount, 'is_active' => true],
        );
    }

    /**
     * Menjalankan proxy bidding setelah `$challenger` menawar dengan batas `$challengerMax`.
     * Lawan terkuat (max tertinggi; jika sama, yang lebih dulu memasang) otomatis membalas.
     */
    private function resolveProxies(Lot $lot, User $challenger, int $challengerMax, Carbon $now, array $meta): void
    {
        $rival = AutoBid::where('lot_id', $lot->id)
            ->where('user_id', '!=', $challenger->id)
            ->where('is_active', true)
            ->where('max_amount', '>=', $this->increments->minimumNextBid($lot))
            ->orderByDesc('max_amount')
            ->orderBy('updated_at')
            ->orderBy('id')
            ->first();

        if (! $rival) {
            // Tidak ada lawan: bila challenger sudah memimpin, tidak perlu bid tambahan.
            if ($lot->leader_id !== $challenger->id) {
                $this->record($lot, $challenger->id, $this->increments->minimumNextBid($lot), true, $now, $meta);
            }

            return;
        }

        if ($rival->max_amount >= $challengerMax) {
            // Lawan menang (max lebih tinggi, atau sama tapi lebih dulu).
            if ($challengerMax > $lot->current_price) {
                $this->record($lot, $challenger->id, $challengerMax, true, $now, $meta);
            }

            $response = $rival->max_amount === $challengerMax
                ? $rival->max_amount
                : min($rival->max_amount, $this->increments->after($challengerMax));

            if ($response > $lot->current_price || $lot->leader_id !== $rival->user_id) {
                $this->record($lot, $rival->user_id, max($response, $lot->current_price), true, $now, []);
            }

            AutoBid::where('lot_id', $lot->id)->where('user_id', $challenger->id)->update(['is_active' => false]);

            return;
        }

        // Challenger menang: lawan mengeluarkan max-nya, challenger membalas satu kelipatan di atasnya.
        if ($rival->max_amount > $lot->current_price) {
            $this->record($lot, $rival->user_id, $rival->max_amount, true, $now, []);
        }
        $rival->update(['is_active' => false]);

        $this->record($lot, $challenger->id, min($challengerMax, $this->increments->after($rival->max_amount)), true, $now, $meta);
    }

    private function record(Lot $lot, int $userId, int $amount, bool $isAuto, Carbon $now, array $meta): Bid
    {
        $prevHash = Bid::where('lot_id', $lot->id)->orderByDesc('id')->value('hash');
        $timestamp = $now->format('Y-m-d H:i:s');

        $bid = Bid::create([
            'lot_id' => $lot->id,
            'user_id' => $userId,
            'amount' => $amount,
            'is_auto' => $isAuto,
            'ip' => $meta['ip'] ?? null,
            'user_agent' => isset($meta['user_agent']) ? mb_substr($meta['user_agent'], 0, 255) : null,
            'prev_hash' => $prevHash,
            'hash' => Bid::computeHash($prevHash, $lot->id, $userId, $amount, $timestamp),
            'created_at' => $now,
        ]);

        $lot->current_price = $amount;
        $lot->leader_id = $userId;
        $lot->bids_count++;

        return $bid;
    }

    /** Anti-sniping + simpan + siarkan + beri tahu pemimpin sebelumnya yang terlampaui. */
    private function finish(Lot $lot, Carbon $now, ?int $previousLeaderId): Lot
    {
        $window = (int) $lot->auction->anti_snipe_minutes;

        if ($window > 0 && $now->diffInSeconds($lot->ends_at, false) < $window * 60) {
            $lot->ends_at = $now->copy()->addMinutes((int) $lot->auction->extend_minutes);
            $lot->extended_count++;

            if ($lot->auction->ends_at->lt($lot->ends_at)) {
                $lot->auction->forceFill(['ends_at' => $lot->ends_at])->save();
            }
        }

        $lot->save();

        BidPlaced::dispatch($lot);

        if ($previousLeaderId && $previousLeaderId !== $lot->leader_id) {
            User::find($previousLeaderId)?->notify(new OutbidNotification($lot, $lot->current_price));
        }

        return $lot;
    }
}
