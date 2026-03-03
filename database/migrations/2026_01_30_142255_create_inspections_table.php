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
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('inspection_templates');
            
            // 🔴 vehicle_id hanya reference ke sistem eksternal
            $table->unsignedBigInteger('vehicle_id')->index();
            
            // Data kendaraan (cached dari API atau input manual)
            $table->string('vehicle_name')->nullable();
            $table->string('license_plate')->nullable(); // mencatat nomor polisi
            $table->integer('mileage')->nullable(); // mencatat km
            $table->string('color')->nullable(); // Warna kendaraan
            $table->string('chassis_number')->nullable(); // Nomor rangka
            $table->string('engine_number')->nullable(); // Nomor mesin
            
            $table->dateTime('inspection_date');
            
            // Status workflow
            $table->enum('status', [
                'draft',
                'in_progress',
                'paused',
                'under_review', // Selesai inspeksi dan dalam proses review sebelum di setujui
                'approved', 
                'rejected',
                'revision',
                'completed',
                'cancelled'
            ])->default('draft');
            
            $table->json('settings')->nullable();
            $table->text('notes')->nullable();
            $table->string('document_path')->nullable(); // File report inspeksi
            $table->string('inspection_code')->nullable(); // kode untuk akses laporan PDF
            
            // Index untuk performa
            $table->index('status');
            $table->index('inspection_date');
            $table->index('inspection_code');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
