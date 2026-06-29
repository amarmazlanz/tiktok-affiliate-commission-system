<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_applications', function (Blueprint $table): void {
            $table->id();
            $table->string('application_reference', 24)->unique();
            $table->string('referral_code', 20)->index();
            $table->foreignId('referrer_affiliate_id')->constrained('affiliates')->restrictOnDelete();
            $table->foreignId('proposed_upline_id')->constrained('affiliates')->restrictOnDelete();
            $table->string('proposed_group_name')->nullable()->index();
            $table->string('full_name');
            $table->string('normalized_name')->index();
            $table->text('nric_encrypted');
            $table->string('nric_hash', 64)->index();
            $table->string('masked_nric', 20);
            $table->string('phone', 30);
            $table->string('normalized_phone', 20)->index();
            $table->string('email');
            $table->string('normalized_email')->index();
            $table->string('tiktok_username');
            $table->string('normalized_tiktok_username')->index();
            $table->string('additional_tiktok_username')->nullable();
            $table->string('normalized_additional_tiktok_username')->nullable();
            $table->index('normalized_additional_tiktok_username', 'aff_apps_norm_add_tiktok_username_idx');
            $table->text('notes')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('duplicate_status')->default('clear')->index();
            $table->text('duplicate_notes')->nullable();
            $table->string('submission_fingerprint', 64)->unique();
            $table->timestamp('consent_at');
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_applications');
    }
};
