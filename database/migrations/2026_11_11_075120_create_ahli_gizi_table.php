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
        Schema::create('ahli_gizi', function (Blueprint $table) {
            $table->comment('Profil profesional tenaga ahli gizi (relasi one-to-one dengan users)');
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete()->comment('FK one-to-one ke tabel users');
            $table->string('spesialisasi')->nullable()->comment('Spesialisasi ahli gizi (e.g. Gizi Keluarga, Gizi Klinis)');
            $table->string('nomor_str')->nullable()->comment('Nomor Surat Tanda Registrasi Tenaga Gizi');
            $table->string('jam_layanan')->nullable()->comment('Jadwal jam layanan konsultasi');
            $table->text('bio')->nullable();
            $table->decimal('tarif_konsultasi', 10, 2)->nullable();
            $table->boolean('is_aktif')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ahli_gizi');
    }
};
