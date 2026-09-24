<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bid bersifat append-only. Setiap bid menyimpan hash dari bid sebelumnya
 * pada lot yang sama (hash chain) sehingga perubahan diam-diam bisa dideteksi.
 */
class Bid extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['lot_id', 'user_id', 'amount', 'is_auto', 'ip', 'user_agent', 'prev_hash', 'hash', 'created_at'];

    protected $hidden = ['ip', 'user_agent'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'is_auto' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function computeHash(?string $prevHash, int $lotId, int $userId, int $amount, string $timestamp): string
    {
        return hash('sha256', implode('|', [$prevHash ?? 'GENESIS', $lotId, $userId, $amount, $timestamp]));
    }

    /**
     * Memverifikasi seluruh rantai hash bid pada sebuah lot.
     * Mengembalikan id bid pertama yang tidak valid, atau null jika utuh.
     */
    public static function firstBrokenLink(int $lotId): ?int
    {
        $prev = null;

        foreach (static::where('lot_id', $lotId)->orderBy('id')->cursor() as $bid) {
            $expected = static::computeHash($prev, $bid->lot_id, $bid->user_id, $bid->amount, $bid->created_at->format('Y-m-d H:i:s'));

            if ($bid->prev_hash !== $prev || ! hash_equals($expected, $bid->hash)) {
                return $bid->id;
            }

            $prev = $bid->hash;
        }

        return null;
    }
}
