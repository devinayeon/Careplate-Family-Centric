<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// SubmitFoodRequest accepts a source_url of up to 500 characters; the original columns were varchar(255), which
// MySQL strict mode rejects (SQLite never enforced the length). Not part of any index.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['foods', 'food_submissions'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('source_url', 500)->nullable()->change());
        }
    }

    public function down(): void
    {
        foreach (['foods', 'food_submissions'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->string('source_url')->nullable()->change());
        }
    }
};
