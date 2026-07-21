<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tiktok_shop_tokens', function (Blueprint $table) {
            $table->dateTime('expires_at')->nullable()->change();
            $table->dateTime('refresh_token_expires_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tiktok_shop_tokens', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->change();
            $table->timestamp('refresh_token_expires_at')->nullable()->change();
        });
    }
};
