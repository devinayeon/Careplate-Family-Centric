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
        Schema::create('makanan', function (Blueprint $table) {
            $table->comment('Master database makanan Indonesia dengan nutrisi lengkap');
            $table->id();
            $table->string('nama')->index()->comment('Nama makanan (e.g., Nasi Goreng)');
            $table->decimal('energi', 8, 2)->comment('Kalori per porsi');
            $table->decimal('lemak', 8, 2)->comment('Fat dalam gram');
            $table->decimal('protein', 8, 2)->comment('Protein dalam gram');
            $table->decimal('kuantitas', 8, 2)->comment('Ukuran porsi default');
            $table->string('satuan')->comment('Unit (gram, ml, porsi, buah)');
            $table->decimal('karbohidrat', 8, 2)->comment('Carbs dalam gram');
            $table->foreignId('kategori_makanan_id')->constrained('kategori_makanan')->cascadeOnDelete();
            $table->text('foto_gizi')->nullable()->comment('Path foto label nutrisi');
            $table->text('foto_makanan')->nullable()->comment('Path foto makanan');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('makanan');
    }
};
