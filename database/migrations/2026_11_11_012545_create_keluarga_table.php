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
        Schema::create('keluarga', function (Blueprint $table) {
            $table->comment('Family groups untuk collective health tracking');
            $table->id();
            $table->string('nama_keluarga')->comment('Nama keluarga (e.g., Keluarga Santoso)');
            $table->string('kode_keluarga', 6)->nullable()->unique()->comment('6-digit unique code untuk join family');
            $table->foreignId('kepala_keluarga_id')->constrained('pengguna')->cascadeOnDelete()->comment('FK ke pengguna (family head)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('keluarga');
    }
};
