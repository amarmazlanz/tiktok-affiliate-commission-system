<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->timestamp('password_reset_at')->nullable()->after('status');
            $table->foreignId('password_reset_by')->nullable()->after('password_reset_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('password_reset_by');
            $table->dropColumn('password_reset_at');
        });
    }
};
