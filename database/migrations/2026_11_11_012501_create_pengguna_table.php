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
        Schema::create('pengguna', function (Blueprint $table) {
            $table->comment('Profile kesehatan dan data pribadi user');
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('FK ke tabel users');
            $table->string('jenis_kelamin')->nullable()->comment('Laki-laki / Perempuan');
            $table->decimal('berat_badan', 8, 2)->nullable()->comment('Berat dalam kg');
            $table->decimal('tinggi_badan', 8, 2)->nullable()->comment('Tinggi dalam cm');
            $table->date('tanggal_lahir')->nullable()->comment('Untuk kalkulasi usia');
            $table->string('aktivitas_fisik')->nullable()->comment('Sedentary, Light, Moderate, Active, Very Active');
            $table->decimal('bmi', 8, 2)->nullable()->comment('Body Mass Index (calculated)');
            $table->decimal('kalori', 8, 2)->nullable()->comment('Target kalori harian (Harris-Benedict)');
            $table->unsignedBigInteger('keluarga_id')->nullable()->index()->comment('FK ke tabel keluarga (nullable)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengguna');
    }
};
