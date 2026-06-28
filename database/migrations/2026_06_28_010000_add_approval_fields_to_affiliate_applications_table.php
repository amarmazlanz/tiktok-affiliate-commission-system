<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliate_applications', function (Blueprint $table): void {
            $table->foreignId('approved_affiliate_id')->nullable()->after('reviewed_by')->constrained('affiliates')->nullOnDelete();
            $table->foreignId('approved_upline_id')->nullable()->after('approved_affiliate_id')->constrained('affiliates')->nullOnDelete();
            $table->string('approved_group_name')->nullable()->index()->after('approved_upline_id');
            $table->string('approved_affiliate_type')->nullable()->after('approved_group_name');
            $table->string('approved_position')->nullable()->after('approved_affiliate_type');
            $table->text('upline_change_reason')->nullable()->after('approved_position');
            $table->text('approval_notes')->nullable()->after('upline_change_reason');
        });
    }

    public function down(): void
    {
        Schema::table('affiliate_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_affiliate_id');
            $table->dropConstrainedForeignId('approved_upline_id');
            $table->dropColumn([
                'approved_affiliate_type',
                'approved_group_name',
                'approved_position',
                'upline_change_reason',
                'approval_notes',
            ]);
        });
    }
};
