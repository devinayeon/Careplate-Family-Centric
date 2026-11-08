<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_turns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('chat_sessions')->cascadeOnDelete();
            $table->uuid('client_turn_id');
            $table->integer('sequence');
            $table->string('request_hash');
            $table->string('status');
            $table->string('token');
            $table->integer('generation');
            $table->timestamp('lease_expires_at')->nullable();
            $table->text('message')->nullable();
            $table->uuid('food_id')->nullable();
            $table->decimal('amount_g', 10, 2)->nullable();
            $table->string('mode')->nullable();
            $table->json('response')->nullable();
            $table->json('citations')->nullable();
            $table->json('food_refs')->nullable();
            $table->string('error_code')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'client_turn_id']);
            $table->unique(['session_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_turns');
    }
};
