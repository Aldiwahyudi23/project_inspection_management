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
        Schema::create('inspection_repair_estimations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inspection_id')
                ->constrained()
                ->cascadeOnDelete();

            // Referensi struktur
           $table->json('related_sources')->nullable();

            // Informasi perbaikan
            $table->string('part_name');
            $table->text('repair_description')->nullable();

            // Status & urgensi
            $table->enum('urgency', ['immediate', 'long_term'])->default('immediate');
            $table->enum('status', ['required', 'recommended', 'optional'])->default('required');

            // Estimasi biaya
            $table->decimal('estimated_cost', 14, 2)->default(0);

            // Catatan
            $table->text('notes')->nullable();

            // Audit mengambil name dari user nantinya
            $table->string('created_by')->nullable();

            $table->timestamps();

            $table->index(['inspection_id', 'urgency', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspection_repair_estimations');
    }
};
