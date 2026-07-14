<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table): void {
            $table->boolean('is_manager')->default(false)->after('status');
        });

        DB::table('affiliates')
            ->whereIn('affiliate_code', ['SWG-0001', 'AUR-0002', 'TIT-0001', 'KAI-0001'])
            ->update(['is_manager' => true]);
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table): void {
            $table->dropColumn('is_manager');
        });
    }
};
