<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_applications', function (Blueprint $table): void {
            $table->string('proposed_affiliate_type', 20)->nullable()->after('proposed_group_name');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_applications', function (Blueprint $table): void {
            $table->dropColumn('proposed_affiliate_type');
        });
    }
};
