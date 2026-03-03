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
        Schema::create('damage_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('damage_id')
                ->constrained('damages')
                ->cascadeOnDelete();

            $table->string('unit');                  // panel, point, area
            $table->decimal('price', 12, 2);         // 500000.00
            $table->string('currency', 10)->default('IDR');
            $table->string('applies_to')->nullable();// body, engine, bumper

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damage_prices');
    }
};
