<?php

namespace App\Enums;

trait HasOptions
{
    /**
     * Daftar opsi untuk dropdown / badge di frontend.
     *
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], self::cases());
    }
}
