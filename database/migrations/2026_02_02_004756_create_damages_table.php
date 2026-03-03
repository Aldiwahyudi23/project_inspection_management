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
        Schema::create('damages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('damage_category_id')
                ->constrained('damage_categories')
                ->cascadeOnDelete();

            $table->string('label');              // Penyok Besar
            $table->string('value')->unique();    // DENT_LARGE
            $table->text('handling')->nullable(); // Needs body repair

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damages');
    }
};
