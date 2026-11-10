<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Separate from the auth `sessions` table.
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('state');
            $table->integer('generation')->default(1);
            $table->uuid('active_turn_id')->nullable();
            $table->string('active_token')->nullable();
            $table->timestamp('active_lease_expires_at')->nullable();
            $table->integer('next_sequence')->default(1);
            $table->uuid('last_food_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
