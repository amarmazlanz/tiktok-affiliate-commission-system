<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('affiliate_code')->nullable()->unique()->after('email');
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->string('affiliate_code')->nullable()->unique()->after('user_id');
            $table->string('group_name')->nullable()->index()->after('upline_id');
            $table->string('name_normalized')->nullable()->index()->after('name');
            $table->text('raw_upline_text')->nullable()->after('phone');
            $table->json('raw_import_data')->nullable()->after('raw_upline_text');
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropColumn([
                'affiliate_code',
                'group_name',
                'name_normalized',
                'raw_upline_text',
                'raw_import_data',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('affiliate_code');
        });
    }
};
