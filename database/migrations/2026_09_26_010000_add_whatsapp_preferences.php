<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Notifikasi transaksi (terlampaui, menang, invoice) aktif secara default; bisa dimatikan di profil.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('whatsapp_notifications')->default(true)->after('phone');
        });

        Schema::table('consignors', function (Blueprint $table) {
            $table->boolean('whatsapp_notifications')->default(true)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('whatsapp_notifications'));
        Schema::table('consignors', fn (Blueprint $table) => $table->dropColumn('whatsapp_notifications'));
    }
};
