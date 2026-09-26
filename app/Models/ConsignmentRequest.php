<?php

namespace App\Models;

use App\Enums\ConsignmentRequestStatus;
use App\Models\Concerns\HasSequentialCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/** Pengajuan titip barang dari calon penitip (form publik). */
class ConsignmentRequest extends Model
{
    use HasSequentialCode;

    public const HANDOVER = ['antar' => 'Saya antar ke lokasi', 'jemput' => 'Minta dijemput'];

    protected $fillable = [
        'user_id', 'name', 'phone', 'email', 'city', 'category_id', 'title', 'description',
        'condition', 'expected_price', 'handover', 'photos', 'ip',
    ];

    protected $hidden = ['ip'];

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'status' => ConsignmentRequestStatus::class,
            'expected_price' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public static function codePrefix(): string
    {
        return 'TTP';
    }

    protected static function booted(): void
    {
        static::creating(fn (ConsignmentRequest $r) => $r->status ??= ConsignmentRequestStatus::New);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function consignor(): BelongsTo
    {
        return $this->belongsTo(Consignor::class)->withTrashed();
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [ConsignmentRequestStatus::New, ConsignmentRequestStatus::Reviewing], true);
    }

    /** Link status untuk pengaju (tanpa login), berlaku 90 hari. */
    public function statusUrl(): string
    {
        return URL::temporarySignedRoute('consign.status', now()->addDays(90), ['consignmentRequest' => $this->code]);
    }
}
