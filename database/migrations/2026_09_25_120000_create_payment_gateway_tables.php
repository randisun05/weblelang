<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uang masuk lewat payment gateway (invoice pemenang, uang jaminan, ...).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->morphs('payable');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('gateway', 20);
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending')->index();
            $table->string('method', 50)->nullable();
            $table->string('provider_ref')->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        // Uang keluar lewat disbursement (payout ke penitip, refund jaminan, ...).
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->morphs('payable');
            $table->string('gateway', 20);
            $table->unsignedBigInteger('amount');
            $table->string('bank_code', 20);
            $table->text('account_number');
            $table->string('account_holder');
            $table->string('status', 20)->default('pending')->index();
            $table->string('provider_ref')->nullable();
            $table->string('failure_reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        // Rekening peserta untuk pengembalian uang jaminan.
        Schema::table('users', function (Blueprint $table) {
            $table->string('bank_name', 20)->nullable()->after('address');
            $table->text('bank_account')->nullable()->after('bank_name');
            $table->string('bank_holder')->nullable()->after('bank_account');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['bank_name', 'bank_account', 'bank_holder']));
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('payments');
    }
};
