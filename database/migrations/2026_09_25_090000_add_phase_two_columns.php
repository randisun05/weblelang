<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auction_registrations', function (Blueprint $table) {
            // held = jaminan ditahan, refunded = dikembalikan, forfeited = hangus (wanprestasi)
            $table->string('deposit_status', 20)->nullable()->after('status');
            $table->timestamp('deposit_settled_at')->nullable()->after('deposit_status');
        });

        Schema::table('consignors', function (Blueprint $table) {
            // Nonce yang ikut ditandatangani pada link portal; diganti untuk mencabut semua link lama.
            $table->string('portal_nonce', 40)->nullable()->after('notes');
        });

        Schema::table('lots', function (Blueprint $table) {
            // Penanda notifikasi "segera berakhir" sudah dikirim (dikirim sekali per lot).
            $table->timestamp('ending_notified_at')->nullable()->after('closed_at');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('cancel_reason')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('auction_registrations', fn (Blueprint $table) => $table->dropColumn(['deposit_status', 'deposit_settled_at']));
        Schema::table('consignors', fn (Blueprint $table) => $table->dropColumn('portal_nonce'));
        Schema::table('lots', fn (Blueprint $table) => $table->dropColumn('ending_notified_at'));
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('cancel_reason'));
    }
};
