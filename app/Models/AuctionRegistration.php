<?php

namespace App\Models;

use App\Enums\DepositStatus;
use App\Enums\RegistrationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pendaftaran peserta pada sesi yang mensyaratkan uang jaminan. */
class AuctionRegistration extends Model
{
    protected $fillable = ['auction_id', 'user_id', 'deposit_proof', 'note'];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'deposit_status' => DepositStatus::class,
            'deposit_settled_at' => 'datetime',
        ];
    }

    public function auction(): BelongsTo
    {
        return $this->belongsTo(Auction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
