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
        Schema::create('inspection_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->onDelete('cascade');
            $table->foreignId('inspection_item_id')->nullable()->constrained('inspection_items')->onDelete('set null');
            $table->string('image_path');
            $table->string('caption')->nullable();
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
        Schema::dropIfExists('inspection_images');
    }
};
