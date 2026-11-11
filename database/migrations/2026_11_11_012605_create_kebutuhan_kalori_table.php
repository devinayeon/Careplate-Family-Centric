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
        Schema::create('kebutuhan_kalori', function (Blueprint $table) {
            $table->comment('Historical calorie calculations dengan Harris-Benedict formula');
            $table->id();
            $table->decimal('skor', 8, 2)->comment('Kalori harian yang dibutuhkan');
            $table->string('keterangan')->comment('Deskripsi status (e.g., Maintenance, Deficit, Surplus)');
            $table->string('jenis_kelamin')->comment('Gender saat kalkulasi');
            $table->decimal('berat_badan', 8, 2)->comment('Berat saat kalkulasi (kg)');
            $table->decimal('tinggi_badan', 8, 2)->comment('Tinggi saat kalkulasi (cm)');
            $table->unsignedInteger('usia')->comment('Usia saat kalkulasi (tahun)');
            $table->string('aktivitas_fisik')->comment('Activity level multiplier');
            $table->foreignId('pengguna_id')->constrained('pengguna')->cascadeOnDelete();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kebutuhan_kalori');
    }
};
