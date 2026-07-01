<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('affiliate_badges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliate_id')->constrained()->cascadeOnDelete();
            $table->string('badge_key', 100);
            $table->timestamp('earned_at');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['affiliate_id', 'badge_key']);
            $table->index('affiliate_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliate_badges');
    }
};
