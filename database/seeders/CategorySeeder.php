<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Kategori bawaan beserta atribut dinamisnya. Tambah kategori baru dari
 * panel admin tanpa perlu migrasi — inilah yang membuat platform reusable.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Elektronik & Gadget', 'elektronik', '💻', [
                ['key' => 'merk', 'label' => 'Merk', 'type' => 'text', 'required' => true],
                ['key' => 'model', 'label' => 'Model / Seri', 'type' => 'text', 'required' => false],
                ['key' => 'garansi', 'label' => 'Garansi', 'type' => 'select', 'options' => ['Ya', 'Tidak'], 'required' => false],
            ]],
            ['Kendaraan', 'kendaraan', '🏍️', [
                ['key' => 'merk', 'label' => 'Merk', 'type' => 'text', 'required' => true],
                ['key' => 'tahun', 'label' => 'Tahun', 'type' => 'number', 'required' => true],
                ['key' => 'nopol', 'label' => 'Nomor Polisi', 'type' => 'text', 'required' => false],
                ['key' => 'kilometer', 'label' => 'Kilometer', 'type' => 'number', 'required' => false],
            ]],
            ['Jam & Perhiasan', 'jam-perhiasan', '⌚', [
                ['key' => 'merk', 'label' => 'Merk', 'type' => 'text', 'required' => false],
                ['key' => 'material', 'label' => 'Material', 'type' => 'text', 'required' => false],
                ['key' => 'kelengkapan', 'label' => 'Kelengkapan', 'type' => 'select', 'options' => ['Fullset', 'Box saja', 'Unit saja'], 'required' => false],
            ]],
            ['Koleksi & Seni', 'koleksi-seni', '🎨', [
                ['key' => 'seniman', 'label' => 'Seniman / Pembuat', 'type' => 'text', 'required' => false],
                ['key' => 'tahun', 'label' => 'Tahun', 'type' => 'text', 'required' => false],
                ['key' => 'media', 'label' => 'Media / Bahan', 'type' => 'text', 'required' => false],
            ]],
            ['Furnitur & Rumah Tangga', 'furnitur', '🪑', [
                ['key' => 'material', 'label' => 'Material', 'type' => 'text', 'required' => false],
                ['key' => 'dimensi', 'label' => 'Dimensi', 'type' => 'text', 'required' => false],
            ]],
        ];

        foreach ($categories as [$name, $slug, $icon, $schema]) {
            Category::updateOrCreate(['slug' => $slug], ['name' => $name, 'icon' => $icon, 'attribute_schema' => $schema]);
        }
    }
}
