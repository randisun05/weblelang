<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Memberi kode manusiawi berurutan, mis. BRG-2026-00012, berbasis primary key
 * sehingga aman dari tabrakan saat dibuat bersamaan.
 */
trait HasSequentialCode
{
    abstract public static function codePrefix(): string;

    public static function codeColumn(): string
    {
        return 'code';
    }

    protected static function bootHasSequentialCode(): void
    {
        static::creating(function ($model) {
            $column = static::codeColumn();
            $model->{$column} ??= 'TMP-'.Str::uuid();
        });

        static::created(function ($model) {
            $column = static::codeColumn();

            if (str_starts_with((string) $model->{$column}, 'TMP-')) {
                $model->{$column} = sprintf('%s-%s-%05d', static::codePrefix(), now()->format('Y'), $model->getKey());
                $model->saveQuietly();
            }
        });
    }
}
