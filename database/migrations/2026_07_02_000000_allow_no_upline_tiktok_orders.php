<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tiktok_orders') || Schema::hasColumn('tiktok_orders', 'sales_source')) {
            return;
        }

        Schema::table('tiktok_orders', function (Blueprint $table): void {
            $table->string('sales_source')->default('mapped_affiliate')->index()->after('affiliate_id');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('tiktok_orders', function (Blueprint $table): void {
                $table->dropForeign(['affiliate_id']);
            });

            DB::statement('ALTER TABLE tiktok_orders MODIFY affiliate_id BIGINT UNSIGNED NULL');

            Schema::table('tiktok_orders', function (Blueprint $table): void {
                $table->foreign('affiliate_id')->references('id')->on('affiliates')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tiktok_orders')) {
            return;
        }

        if (Schema::hasColumn('tiktok_orders', 'sales_source')) {
            Schema::table('tiktok_orders', function (Blueprint $table): void {
                $table->dropIndex(['sales_source']);
                $table->dropColumn('sales_source');
            });
        }
    }
};
