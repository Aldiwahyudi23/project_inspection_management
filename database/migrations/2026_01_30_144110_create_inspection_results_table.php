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
        Schema::create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->onDelete('cascade');
            $table->foreignId('inspection_item_id')->constrained('inspection_items')->onDelete('cascade'); // relasi ke item inspeksi
            $table->string('status')->nullable(); // Untuk radio/select options
            $table->text('note')->nullable(); // Untuk text input
            $table->json('extra_data')->nullable(); // Untuk data tambahan
            $table->timestamps();
            
            // Index untuk query performa
            $table->index(['inspection_id', 'inspection_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_results');
    }
};
