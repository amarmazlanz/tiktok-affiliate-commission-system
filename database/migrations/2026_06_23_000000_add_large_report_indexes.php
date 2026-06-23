<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_entries', function (Blueprint $table) {
            $table->index(['commission_run_id', 'receiver_affiliate_id'], 'ce_run_receiver_idx');
            $table->index(['commission_run_id', 'source_affiliate_id'], 'ce_run_source_idx');
            $table->index(['commission_run_id', 'commission_type'], 'ce_run_type_idx');
            $table->index(['commission_run_id', 'tiktok_order_id'], 'ce_run_order_idx');
            $table->index(['commission_run_id', 'commission_type', 'level'], 'ce_run_type_level_idx');
        });

        Schema::table('tiktok_orders', function (Blueprint $table) {
            $table->index(['order_status', 'time_commission_paid'], 'orders_status_commission_paid_idx');
            $table->index(['order_status', 'time_created'], 'orders_status_created_idx');
            $table->index(['affiliate_id', 'time_created'], 'orders_affiliate_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tiktok_orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_commission_paid_idx');
            $table->dropIndex('orders_status_created_idx');
            $table->dropIndex('orders_affiliate_created_idx');
        });

        Schema::table('commission_entries', function (Blueprint $table) {
            $table->dropIndex('ce_run_receiver_idx');
            $table->dropIndex('ce_run_source_idx');
            $table->dropIndex('ce_run_type_idx');
            $table->dropIndex('ce_run_order_idx');
            $table->dropIndex('ce_run_type_level_idx');
        });
    }
};
