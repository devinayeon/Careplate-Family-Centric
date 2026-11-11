<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->json('aliases')->nullable();
            $table->string('basis')->default('100g');
            $table->decimal('energy_kcal', 12, 4)->nullable();
            $table->decimal('protein_g', 12, 4)->nullable();
            $table->decimal('fat_g', 12, 4)->nullable();
            $table->decimal('carbohydrate_g', 12, 4)->nullable();
            $table->decimal('sodium_mg', 12, 4)->nullable();
            $table->decimal('sugars_total_g', 12, 4)->nullable();
            $table->string('energy_method')->nullable();
            $table->string('source_provider');
            $table->string('source_id');
            $table->string('source_url')->nullable();
            $table->string('snapshot_version');
            $table->string('source_sha256')->nullable();
            $table->text('source_description')->nullable();
            $table->string('status');
            $table->unsignedInteger('current_revision')->default(0);
            $table->text('search_text')->nullable();
            $table->string('verifier_audit_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_provider', 'source_id', 'snapshot_version']);
            $table->index(['status', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
