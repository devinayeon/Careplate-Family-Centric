<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Meal time of a log (Figma 03 groups logs into Sarapan, Makan Siang, Makan Malam, Camilan).
     * Nullable: rows written before this column existed are placed by their `waktu_konsumsi` when read.
     */
    public function up(): void
    {
        Schema::table('pelacakan_makanan', function (Blueprint $table) {
            $table->enum('waktu_makan', ['sarapan', 'makan_siang', 'makan_malam', 'camilan'])
                ->nullable()->after('waktu_konsumsi')->comment('Waktu makan: sarapan, makan_siang, makan_malam, camilan');
        });
    }

    public function down(): void
    {
        Schema::table('pelacakan_makanan', function (Blueprint $table) {
            $table->dropColumn('waktu_makan');
        });
    }
};
