<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Indikasi kecurangan (shill bidding / akun ganda) untuk ditinjau admin. */
class FraudFlag extends Model
{
    public const RULES = [
        'shared_device' => 'Beberapa akun menawar dari perangkat yang sama',
        'shared_ip' => 'Beberapa akun menawar dari alamat IP yang sama',
        'consignor_match' => 'Penawar memiliki data yang sama dengan penitip barang',
    ];

    protected $fillable = ['lot_id', 'rule', 'severity', 'fingerprint', 'user_ids', 'details'];

    protected function casts(): array
    {
        return ['user_ids' => 'array', 'details' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function label(): string
    {
        return self::RULES[$this->rule] ?? $this->rule;
    }
}
