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
        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('component_id')->constrained('components');
            $table->string('name');
            $table->longText('description')->nullable(); // Deskripsi item inspeksi
            $table->text('check_notes')->nullable(); // Catatan khusus untuk item inspeksi
            $table->integer('sort_order'); // ordering dalam komponen
            $table->boolean('is_active')->default(true);
            $table->string('image_path')->nullable(); // Untuk menyimpan path gambar item inspeksi
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['name', 'component_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
    }
};
