<?php

namespace App\Services\Security;

use App\Models\Bid;
use App\Models\FraudFlag;
use App\Models\Lot;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;

/**
 * Mendeteksi indikasi shill bidding pada sebuah lot. Tidak memblokir otomatis —
 * hanya membuat "flag" untuk ditinjau admin, karena satu IP bisa dipakai bersama
 * secara wajar (kantor, keluarga, operator seluler).
 */
class FraudDetector
{
    public function scanLot(Lot $lot): int
    {
        $lot->loadMissing('item.consignor');
        $bids = Bid::where('lot_id', $lot->id)->where('is_auto', false)->get(['user_id', 'ip', 'device_id']);
        $created = 0;

        // 1. Perangkat sama dipakai beberapa akun → indikasi kuat akun ganda.
        foreach ($this->groupsSharing($bids, 'device_id') as $device => $userIds) {
            $created += $this->flag($lot, 'shared_device', 'high', $userIds, ['device' => substr($device, 0, 8).'…']);
        }

        // 2. IP sama dipakai beberapa akun → indikasi sedang (bisa wajar).
        foreach ($this->groupsSharing($bids, 'ip') as $ip => $userIds) {
            $created += $this->flag($lot, 'shared_ip', 'medium', $userIds, ['ip' => $ip]);
        }

        // 3. Data penawar sama dengan data penitip barang (HP / NIK).
        $consignor = $lot->item?->consignor;
        if ($consignor) {
            $bidders = User::whereIn('id', $bids->pluck('user_id')->unique())->get();
            foreach ($bidders as $user) {
                $matches = array_keys(array_filter([
                    'telepon' => $this->samePhone($user->phone, $consignor->phone),
                    'nik' => $user->nik && $consignor->nik && hash_equals($consignor->nik, $user->nik),
                ]));
                if ($matches) {
                    $created += $this->flag($lot, 'consignor_match', 'high', [$user->id], ['cocok' => $matches, 'penitip' => $consignor->code]);
                }
            }
        }

        return $created;
    }

    /** @return Collection<string, array<int>> nilai → daftar user (≥2 akun berbeda) */
    private function groupsSharing(Collection $bids, string $field): Collection
    {
        return $bids->filter(fn ($b) => filled($b->{$field}))
            ->groupBy($field)
            ->map(fn ($group) => $group->pluck('user_id')->unique()->sort()->values()->all())
            ->filter(fn ($userIds) => count($userIds) > 1);
    }

    private function flag(Lot $lot, string $rule, string $severity, array $userIds, array $details): int
    {
        sort($userIds);
        $fingerprint = hash('sha256', implode('|', [$lot->id, $rule, implode(',', $userIds)]));

        if (FraudFlag::where('fingerprint', $fingerprint)->exists()) {
            return 0;
        }

        $flag = FraudFlag::create([
            'lot_id' => $lot->id, 'rule' => $rule, 'severity' => $severity,
            'fingerprint' => $fingerprint, 'user_ids' => $userIds, 'details' => $details,
        ]);
        AuditLogger::log('fraud.flagged', $flag, ['rule' => $rule, 'users' => $userIds]);

        return 1;
    }

    private function samePhone(?string $a, ?string $b): bool
    {
        $normalize = fn (?string $p) => preg_replace('/^(62|0)/', '', preg_replace('/\D/', '', (string) $p));

        return $a && $b && strlen($normalize($a)) >= 8 && $normalize($a) === $normalize($b);
    }
}
