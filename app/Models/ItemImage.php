<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ItemImage extends Model
{
    protected $fillable = ['item_id', 'path', 'sort_order'];

    protected $appends = ['url'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    public function getUrlAttribute(): string
    {
        return $this->url();
    }
}
