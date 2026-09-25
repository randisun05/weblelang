<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Penawaran otomatis (proxy bid) dengan batas maksimum. */
class AutoBid extends Model
{
    protected $fillable = ['lot_id', 'user_id', 'max_amount', 'is_active'];

    protected function casts(): array
    {
        return ['max_amount' => 'integer', 'is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
