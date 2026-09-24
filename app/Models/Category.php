<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori barang. `attribute_schema` berisi daftar field dinamis, mis:
 * [{"key":"merk","label":"Merk","type":"text","required":true}, ...]
 * sehingga platform bisa dipakai untuk jenis barang apa pun tanpa migrasi baru.
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'icon', 'attribute_schema'];

    protected function casts(): array
    {
        return ['attribute_schema' => 'array'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
