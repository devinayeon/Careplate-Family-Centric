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
        Schema::create('konsultasi', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_konsultasi', 50)->unique()->comment('Nomor tiket konsultasi unik');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->comment('Pengguna/klien yang mengajukan');
            $table->foreignId('ahli_gizi_id')->constrained('ahli_gizi')->cascadeOnDelete()->comment('Ahli gizi yang dituju');
            $table->enum('target_tipe', ['diri_sendiri', 'orang_lain'])->default('diri_sendiri');
            $table->string('nama_pasien');
            $table->unsignedInteger('usia_pasien');
            $table->string('topik');
            $table->text('keluhan');
            $table->json('data_kesehatan')->nullable()->comment('Snapshot BMI, energi, sayur, dll');
            $table->enum('status', ['menunggu', 'berlangsung', 'selesai', 'ditolak'])->default('menunggu');
            $table->text('alasan_penolakan')->nullable();
            $table->timestamp('disetujui_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamp('ditolak_pada')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['ahli_gizi_id', 'status']);
        });

        Schema::create('pesan_konsultasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('konsultasi_id')->constrained('konsultasi')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->enum('tipe_pesan', ['teks', 'meeting', 'sistem', 'gambar'])->default('teks');
            $table->text('pesan');
            $table->json('metadata')->nullable()->comment('Data khusus untuk meeting link, attachment, dsb');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['konsultasi_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pesan_konsultasi');
        Schema::dropIfExists('konsultasi');
    }
};
