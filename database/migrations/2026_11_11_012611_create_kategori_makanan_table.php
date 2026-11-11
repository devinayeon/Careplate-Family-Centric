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
        Schema::create('kategori_makanan', function (Blueprint $table) {
            $table->comment('Kategori makanan untuk organizational purposes');
            $table->id();
            $table->string('kategori')->unique()->comment('Nama kategori (Sarapan, Makan Siang, Snack)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kategori_makanan');
    }
};
