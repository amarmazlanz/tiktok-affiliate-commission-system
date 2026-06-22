<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('email')->nullable()->change();
            $table->string('affiliate_type')->default('inhouse')->index()->after('group_name');
            $table->string('raw_l1')->nullable()->after('raw_upline_text');
            $table->string('raw_l2')->nullable()->after('raw_l1');
            $table->string('raw_l3')->nullable()->after('raw_l2');
            $table->string('hierarchy_import_status')->nullable()->index()->after('raw_l3');
            $table->string('hierarchy_import_remark')->nullable()->after('hierarchy_import_status');
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropColumn([
                'affiliate_type',
                'raw_l1',
                'raw_l2',
                'raw_l3',
                'hierarchy_import_status',
                'hierarchy_import_remark',
            ]);

            $table->foreignId('user_id')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
        });

        Schema::table('affiliates', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
