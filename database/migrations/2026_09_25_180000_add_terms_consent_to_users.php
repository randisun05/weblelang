<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Bukti persetujuan (UU PDP): versi S&K + Kebijakan Privasi yang disetujui dan kapan.
            $table->string('terms_version', 20)->nullable()->after('is_blocked');
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_version');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['terms_version', 'terms_accepted_at']));
    }
};
