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
        Schema::create('pelacakan_makanan', function (Blueprint $table) {
            $table->comment('Food tracking log per user per day');
            $table->id();
            $table->foreignId('pengguna_id')->constrained('pengguna')->cascadeOnDelete();
            $table->foreignId('makanan_id')->constrained('makanan')->cascadeOnDelete();
            $table->date('tanggal_konsumsi')->comment('Tanggal makan');
            $table->time('waktu_konsumsi')->comment('Jam makan');
            $table->decimal('jumlah_porsi', 8, 2)->comment('Jumlah porsi yang dimakan');
            $table->decimal('total_kalori', 10, 2)->comment('Calculated total calories');
            $table->timestamps();

            $table->index(['pengguna_id', 'tanggal_konsumsi']);
            $table->index('tanggal_konsumsi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pelacakan_makanan');
    }
};
