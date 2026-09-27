<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            // ID perangkat acak dari cookie (bukan fingerprint) untuk mendeteksi akun ganda.
            $table->string('device_id', 64)->nullable()->after('user_agent')->index();
        });

        Schema::create('fraud_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule', 30);               // shared_device | shared_ip | consignor_match
            $table->string('severity', 10);           // high | medium
            $table->string('fingerprint', 64)->unique(); // cegah flag ganda untuk kasus yang sama
            $table->json('user_ids');
            $table->json('details')->nullable();
            $table->string('status', 12)->default('open')->index(); // open | dismissed | confirmed
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_flags');
        Schema::table('bids', fn (Blueprint $table) => $table->dropColumn('device_id'));
    }
};
