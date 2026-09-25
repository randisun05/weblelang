<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            // open = terbuka berjadwal, sealed = penawaran tertutup, live = dipandu juru lelang
            $table->string('method', 10)->default('open')->after('status');
            $table->string('stream_url')->nullable()->after('method');
        });

        Schema::table('lots', function (Blueprint $table) {
            $table->unsignedBigInteger('buy_now_price')->nullable()->after('reserve_price');
            // bid | buy_now | live — cara lot terjual
            $table->string('sold_via', 10)->nullable()->after('status');
            // Panggilan juru lelang (0, 1 = "pertama", 2 = "kedua"); direset setiap ada bid baru.
            $table->unsignedTinyInteger('live_calls')->default(0)->after('extended_count');
            $table->timestamp('live_called_at')->nullable()->after('live_calls');
        });
    }

    public function down(): void
    {
        Schema::table('lots', fn (Blueprint $table) => $table->dropColumn(['buy_now_price', 'sold_via', 'live_calls', 'live_called_at']));
        Schema::table('auctions', fn (Blueprint $table) => $table->dropColumn(['method', 'stream_url']));
    }
};
