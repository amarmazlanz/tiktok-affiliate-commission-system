<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiktok_orders', function (Blueprint $table): void {
            $table->foreignId('order_import_id')->nullable()->after('id')->constrained('order_imports')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tiktok_orders', function (Blueprint $table): void {
            $table->dropForeign(['order_import_id']);
            $table->dropColumn('order_import_id');
        });
    }
};
