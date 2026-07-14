<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_name');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('inserted_orders')->default(0);
            $table->unsignedInteger('updated_orders')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('matched_orders')->default(0);
            $table->unsignedInteger('no_upline_orders')->default(0);
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_imports');
    }
};
