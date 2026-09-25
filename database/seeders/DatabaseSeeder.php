<?php

namespace Database\Seeders;

use App\Enums\AuctionMethod;
use App\Enums\AuctionStatus;
use App\Enums\ItemStatus;
use App\Enums\LotStatus;
use App\Enums\Role;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Consignor;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        // Akun petugas. GANTI password setelah login pertama di production!
        foreach ([
            ['Super Admin', 'superadmin@weblelang.test', Role::SuperAdmin],
            ['Admin Lelang', 'admin@weblelang.test', Role::Admin],
            ['Staf Gudang', 'staf@weblelang.test', Role::Staff],
        ] as [$name, $email, $role]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => Hash::make('password')]);
            $user->forceFill(['role' => $role])->save();
        }

        if (! app()->environment('local', 'testing')) {
            return;
        }

        // ---- Data demo (hanya lokal) ----
        User::factory()->verified()->create(['name' => 'Budi Peserta', 'email' => 'budi@contoh.test']);
        User::factory()->verified()->create(['name' => 'Sari Peserta', 'email' => 'sari@contoh.test']);
        User::factory()->create(['name' => 'Andi Belum KYC', 'email' => 'andi@contoh.test']);

        $consignors = Consignor::factory()->count(4)->create();
        $categories = Category::all()->keyBy('slug');

        $catalog = [
            ['elektronik', 'Kamera Mirrorless Sony A7 III + Lensa 28-70', 12_000_000, ['merk' => 'Sony', 'model' => 'A7 III', 'garansi' => 'Tidak']],
            ['elektronik', 'MacBook Pro 14" M1 Pro 16/512', 15_000_000, ['merk' => 'Apple', 'model' => 'MacBook Pro 14 M1 Pro', 'garansi' => 'Tidak']],
            ['kendaraan', 'Honda Vario 160 ABS 2023', 22_000_000, ['merk' => 'Honda', 'tahun' => '2023', 'nopol' => 'B 1234 XYZ', 'kilometer' => '8500']],
            ['jam-perhiasan', 'Jam Tangan Seiko Presage Cocktail Time', 4_000_000, ['merk' => 'Seiko', 'material' => 'Stainless steel', 'kelengkapan' => 'Fullset']],
            ['koleksi-seni', 'Lukisan Cat Minyak "Senja di Sawah" 80x60', 3_500_000, ['seniman' => 'Anonim', 'tahun' => '1998', 'media' => 'Cat minyak di kanvas']],
            ['furnitur', 'Meja Makan Jati Solid 6 Kursi', 6_000_000, ['material' => 'Kayu jati', 'dimensi' => '180x90x76 cm']],
        ];

        $items = collect($catalog)->map(fn ($row, $i) => Item::factory()->create([
            'consignor_id' => $consignors[$i % 4]->id,
            'category_id' => $categories[$row[0]]->id,
            'title' => $row[1],
            'reserve_price' => $row[2],
            'estimate_low' => (int) ($row[2] * 1.1),
            'estimate_high' => (int) ($row[2] * 1.5),
            'specs' => $row[3],
            'status' => ItemStatus::Listed,
            'storage_location' => 'Rak A-'.($i + 1),
        ]));

        $live = Auction::factory()->create([
            'title' => 'Lelang Mingguan Elektronik & Kendaraan',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->addHours(6),
            'status' => AuctionStatus::Live,
            'stagger_seconds' => 60,
        ]);

        $upcoming = Auction::factory()->create([
            'title' => 'Lelang Koleksi, Jam & Furnitur',
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(4),
            'status' => AuctionStatus::Published,
        ]);

        foreach ($items as $i => $item) {
            $auction = $i < 3 ? $live : $upcoming;
            $number = $auction->lots()->count() + 1;
            $auction->lots()->create([
                'item_id' => $item->id,
                'lot_number' => $number,
                'starting_price' => (int) round($item->reserve_price * 0.6, -5),
                'reserve_price' => $item->reserve_price,
                'starts_at' => $auction->starts_at,
                'ends_at' => $auction->ends_at->copy()->addSeconds(($number - 1) * $auction->stagger_seconds),
            ])->forceFill(['status' => $i < 3 ? LotStatus::Live : LotStatus::Scheduled])->save();
        }

        // Lot pertama bisa dibeli langsung.
        $live->lots()->orderBy('lot_number')->first()->forceFill(['buy_now_price' => 20_000_000])->save();

        // Sesi penawaran tertutup & sesi live juru lelang.
        $extra = [
            ['elektronik', 'iPhone 14 Pro 256GB Deep Purple', 9_000_000, ['merk' => 'Apple', 'model' => 'iPhone 14 Pro']],
            ['jam-perhiasan', 'Cincin Emas 24K 5 gram', 5_500_000, ['material' => 'Emas 24K', 'kelengkapan' => 'Box saja']],
            ['koleksi-seni', 'Keris Pusaka Luk 7 dengan Warangka Kayu Cendana', 7_000_000, ['seniman' => 'Empu (tidak diketahui)', 'media' => 'Besi pamor']],
            ['kendaraan', 'Toyota Avanza 1.3 G MT 2019', 140_000_000, ['merk' => 'Toyota', 'tahun' => '2019', 'nopol' => 'B 2345 KLM', 'kilometer' => '62000']],
        ];
        $sessions = [
            Auction::factory()->create([
                'title' => 'Lelang Tertutup Perhiasan & Gadget', 'method' => AuctionMethod::Sealed,
                'starts_at' => now()->subHour(), 'ends_at' => now()->addDays(2), 'status' => AuctionStatus::Live,
            ]),
            Auction::factory()->create([
                'title' => 'Lelang Live Kendaraan & Barang Antik', 'method' => AuctionMethod::Live,
                'starts_at' => now()->subMinutes(5), 'ends_at' => now()->addHours(3), 'status' => AuctionStatus::Published,
            ]),
        ];
        foreach ($extra as $i => $row) {
            $item = Item::factory()->create([
                'consignor_id' => $consignors[$i % 4]->id, 'category_id' => $categories[$row[0]]->id, 'title' => $row[1],
                'reserve_price' => $row[2], 'estimate_low' => (int) ($row[2] * 1.1), 'estimate_high' => (int) ($row[2] * 1.4),
                'specs' => $row[3], 'status' => ItemStatus::Listed,
            ]);
            $auction = $sessions[intdiv($i, 2)];
            $auction->lots()->create([
                'item_id' => $item->id, 'lot_number' => $i % 2 + 1,
                'starting_price' => (int) round($item->reserve_price * 0.7, -5), 'reserve_price' => $item->reserve_price,
                'starts_at' => $auction->starts_at, 'ends_at' => $auction->ends_at,
            ])->forceFill(['status' => $auction->method === AuctionMethod::Sealed ? LotStatus::Live : LotStatus::Scheduled])->save();
        }

        Item::factory()->count(2)->create(['consignor_id' => $consignors[0]->id, 'category_id' => $categories['elektronik']->id, 'status' => ItemStatus::Received]);
    }
}
