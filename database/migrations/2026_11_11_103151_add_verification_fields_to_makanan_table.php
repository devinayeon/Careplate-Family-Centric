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
            $table->text('alasan_penolakan')->nullable()->after('status')->comment('Alasan/catatan penolakan pengajuan makanan');
            $table->foreignId('diverifikasi_oleh')->nullable()->after('alasan_penolakan')->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable()->after('diverifikasi_oleh');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('makanan', function (Blueprint $table) {
            $table->dropForeign(['diverifikasi_oleh']);
            $table->dropColumn(['alasan_penolakan', 'diverifikasi_oleh', 'diverifikasi_pada']);
        });
    }
};
