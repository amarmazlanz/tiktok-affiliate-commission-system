<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('affiliates')
            ->where('affiliate_type', 'external')
            ->update(['affiliate_type' => 'online']);

        DB::table('affiliate_applications')
            ->where('approved_affiliate_type', 'external')
            ->update(['approved_affiliate_type' => 'online']);
    }

    public function down(): void
    {
        DB::table('affiliates')
            ->where('affiliate_type', 'online')
            ->update(['affiliate_type' => 'external']);

        DB::table('affiliate_applications')
            ->where('approved_affiliate_type', 'online')
            ->update(['approved_affiliate_type' => 'external']);
    }
};
