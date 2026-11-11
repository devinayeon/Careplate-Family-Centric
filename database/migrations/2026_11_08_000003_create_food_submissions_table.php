<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->integer('revision_counter')->default(1);
            $table->json('candidate');
            $table->string('source_provider');
            $table->string('source_id');
            $table->string('source_url')->nullable();
            $table->string('snapshot_version');
            $table->string('source_sha256')->nullable();
            $table->foreignUuid('food_id')->nullable()->constrained('foods')->nullOnDelete();
            $table->string('decision_hash')->nullable();
            $table->json('decision_result')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['source_provider', 'source_id', 'snapshot_version'], 'food_submissions_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_submissions');
    }
};
