<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('food_id')->constrained('foods')->cascadeOnDelete();
            $table->integer('revision');
            $table->string('action');
            $table->string('status');
            $table->json('snapshot');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_audit_id')->nullable();
            $table->text('note')->nullable();
            $table->string('decision_hash')->nullable();
            $table->json('result')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['food_id', 'revision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_revisions');
    }
};
