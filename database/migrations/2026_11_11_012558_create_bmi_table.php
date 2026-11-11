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
        Schema::create('bmi', function (Blueprint $table) {
            $table->comment('Historical BMI calculations untuk tracking progress');
            $table->id();
            $table->decimal('skor', 8, 2)->comment('BMI score (weight/height²)');
            $table->string('keterangan')->comment('Underweight, Normal, Overweight, Obese');
            $table->decimal('berat_badan', 8, 2)->comment('Berat saat kalkulasi (kg)');
            $table->decimal('tinggi_badan', 8, 2)->comment('Tinggi saat kalkulasi (cm)');
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
        Schema::dropIfExists('bmi');
    }
};
