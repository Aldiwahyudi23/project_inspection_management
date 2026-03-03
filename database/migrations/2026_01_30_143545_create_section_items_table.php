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
        Schema::create('section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('menu_sections');
            $table->foreignId('inspection_item_id')->constrained('inspection_items');
            $table->string('input_type')->default('text'); // text, radio, checkbox, image, etc
            $table->json('settings')->nullable(); // Konfigurasi dinamis per input type
            $table->integer('sort_order'); // ordering dalam section

            // Data status aktif/non-aktif
            $table->boolean('is_active')->default(true);
            
            // Data visibilitas item dalam UI
            $table->boolean('is_visible')->default(true);
            
            // Opsional: tambah is_required jika perlu validasi
            $table->boolean('is_required')->default(false);
            
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['section_id', 'inspection_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_items');
    }
};
