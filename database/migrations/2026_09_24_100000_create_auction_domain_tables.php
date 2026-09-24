<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Semua nilai uang disimpan sebagai BIGINT rupiah (tanpa desimal)
 * supaya tidak ada error pembulatan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('nik')->nullable();
            $table->string('bank_name')->nullable();
            $table->text('bank_account')->nullable();
            $table->string('bank_holder')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(10);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon', 16)->nullable();
            $table->json('attribute_schema')->nullable();
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('consignor_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('condition', 30)->default('bekas_baik');
            $table->json('specs')->nullable();
            $table->unsignedBigInteger('estimate_low')->nullable();
            $table->unsignedBigInteger('estimate_high')->nullable();
            $table->unsignedBigInteger('reserve_price')->default(0);
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->string('status', 20)->default('received')->index();
            $table->string('storage_location')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('inspection_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('item_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20)->default('draft')->index();
            $table->unsignedBigInteger('deposit_amount')->default(0);
            $table->decimal('buyer_premium_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('anti_snipe_minutes')->default(3);
            $table->unsignedSmallInteger('extend_minutes')->default(3);
            $table->unsignedSmallInteger('stagger_seconds')->default(0);
            $table->timestamps();
        });

        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('lot_number');
            $table->unsignedBigInteger('starting_price');
            $table->unsignedBigInteger('reserve_price')->default(0);
            $table->unsignedBigInteger('current_price')->default(0);
            $table->unsignedInteger('bids_count')->default(0);
            $table->foreignId('leader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('winning_bid_id')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->index();
            $table->unsignedSmallInteger('extended_count')->default(0);
            $table->string('status', 20)->default('scheduled')->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['auction_id', 'lot_number']);
        });

        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount');
            $table->boolean('is_auto')->default(false);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64);
            $table->timestamp('created_at', 6)->nullable();

            $table->index(['lot_id', 'amount']);
        });

        Schema::create('auto_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('max_amount');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['lot_id', 'user_id']);
        });

        Schema::create('auction_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('deposit_proof')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['auction_id', 'user_id']);
        });

        Schema::create('watchlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'lot_id']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('lot_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('hammer_price');
            $table->unsignedBigInteger('buyer_premium')->default(0);
            $table->unsignedBigInteger('admin_fee')->default(0);
            $table->unsignedBigInteger('total');
            $table->string('status', 20)->default('unpaid')->index();
            $table->timestamp('due_at');
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_ref')->nullable();
            $table->string('payment_proof')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('settlements', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('consignor_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('hammer_price');
            $table->decimal('commission_rate', 5, 2);
            $table->unsignedBigInteger('commission');
            $table->unsignedBigInteger('other_fees')->default(0);
            $table->unsignedBigInteger('net_amount');
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->string('transfer_proof')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 60)->index();
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'settlements', 'invoices', 'watchlists', 'auction_registrations',
            'auto_bids', 'bids', 'lots', 'auctions', 'item_images', 'items', 'categories', 'consignors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
