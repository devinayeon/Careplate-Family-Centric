<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ai:purge-chats clears chat_sessions.active_turn_id for deleted turns; without an index InnoDB
        // next-key-locks every session row while it does so (REPEATABLE READ full scan).
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->index('active_turn_id', 'chat_sessions_active_turn_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropIndex('chat_sessions_active_turn_id_index');
        });
    }
};
