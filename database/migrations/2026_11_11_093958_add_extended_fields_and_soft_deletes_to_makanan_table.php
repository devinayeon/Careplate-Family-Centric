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
        Schema::table('makanan', function (Blueprint $table) {
            $table->decimal('gula', 8, 2)->default(0)->nullable()->after('karbohidrat')->comment('Gula dalam gram');
            $table->decimal('natrium', 8, 2)->default(0)->nullable()->after('gula')->comment('Natrium dalam mg');
            $table->string('sumber', 150)->default('Tabel Komposisi Pangan')->nullable()->after('natrium')->comment('Sumber data rujukan nutrisi');
            $table->enum('status', ['terverifikasi', 'menunggu', 'ditolak'])->default('terverifikasi')->index()->after('sumber');
            $table->foreignId('pengaju_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->string('nama_pengaju', 150)->default('Admin')->after('pengaju_id');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('makanan', function (Blueprint $table) {
            $table->dropForeign(['pengaju_id']);
            $table->dropColumn(['gula', 'natrium', 'sumber', 'status', 'pengaju_id', 'nama_pengaju']);
            $table->dropSoftDeletes();
        });
    }
};
