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
    Schema::create('stock_movements', function (Blueprint $table) {
        $table->id();

        $table->foreignId('product_id')
            ->constrained()
            ->restrictOnDelete();

        $table->string('type'); // IN or OUT
        $table->unsignedInteger('quantity');
        $table->string('reference_type');
        $table->unsignedBigInteger('reference_id');
        $table->integer('stock_before');
        $table->integer('stock_after');
        $table->timestamps();

        $table->index(['reference_type', 'reference_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
