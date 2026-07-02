<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('upline_id')->nullable()->constrained('affiliates')->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('tiktok_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->string('username');
            $table->string('username_normalized')->unique();
            $table->string('status')->default('active')->index();
            $table->timestamps();
        });

        Schema::create('tiktok_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique();
            $table->foreignId('affiliate_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('sales_source')->default('mapped_affiliate')->index();
            $table->string('creator_username');
            $table->string('creator_username_normalized')->index();
            $table->string('order_status')->index();
            $table->decimal('estimated_commission_base', 12, 2);
            $table->decimal('actual_commission_base', 12, 2)->nullable();
            $table->decimal('actual_commission_payment', 12, 2)->nullable();
            $table->decimal('payment_amount', 12, 2)->nullable();
            $table->string('currency')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->timestamp('time_created')->nullable();
            $table->timestamp('payment_time')->nullable();
            $table->timestamp('time_commission_paid')->nullable();
            $table->string('platform')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });

        Schema::create('commission_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('status')->default('draft')->index();
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('total_commission', 12, 2)->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['month', 'year']);
        });

        Schema::create('commission_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('receiver_affiliate_id')->constrained('affiliates')->restrictOnDelete();
            $table->foreignId('source_affiliate_id')->constrained('affiliates')->restrictOnDelete();
            $table->foreignId('tiktok_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('commission_type')->index();
            $table->unsignedTinyInteger('level')->nullable();
            $table->decimal('rate', 8, 4);
            $table->decimal('base_amount', 12, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_entries');
        Schema::dropIfExists('commission_runs');
        Schema::dropIfExists('tiktok_orders');
        Schema::dropIfExists('tiktok_accounts');
        Schema::dropIfExists('affiliates');
    }
};
