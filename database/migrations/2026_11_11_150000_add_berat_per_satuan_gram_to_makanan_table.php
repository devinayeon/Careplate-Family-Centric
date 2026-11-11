<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grams of ONE unit (`satuan`) of the food: 1 porsi = 150 g, 1 butir = 60 g, 1 ml = 1.2 g.
     * Optional. Gram rows ignore it; for the other units it lets the AI chat convert the serving to per 100 g.
     */
    public function up(): void
    {
        Schema::table('makanan', function (Blueprint $table) {
            $table->decimal('berat_per_satuan_gram', 10, 2)->nullable()->after('satuan')->comment('Berat (gram) satu satuan takaran; opsional, dipakai Chat Gizi');
        });
    }

    public function down(): void
    {
        Schema::table('makanan', function (Blueprint $table) {
            $table->dropColumn('berat_per_satuan_gram');
        });
    }
};
