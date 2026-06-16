<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commission_rate_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->decimal('personal_rate', 8, 4);
            $table->decimal('manager_bonus_rate', 8, 4);
            $table->decimal('l1_rate', 8, 4);
            $table->decimal('l2_rate', 8, 4);
            $table->decimal('l3_rate', 8, 4);
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->unique(['month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commission_rate_settings');
    }
};
