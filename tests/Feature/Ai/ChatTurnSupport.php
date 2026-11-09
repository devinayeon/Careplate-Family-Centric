<?php

use App\Models\ChatSession;
use App\Models\ChatTurn;
use App\Models\Food;
use App\Models\User;
use App\Services\Ai\Chat\ReserveResult;
use App\Services\Ai\Chat\TurnGate;
use Illuminate\Support\Str;

// SYNTHETIC users, sessions and turns only. Shared by the TurnGate and chat history tests.

require_once __DIR__.'/ChatFoodSupport.php';

function ctUser(string $role = 'family_head'): User
{
    $user = User::factory()->create();
    if ($role !== '') {
        $user->assignRole($role);
    }

    return $user;
}

function ctSession(?User $owner = null, array $attrs = []): ChatSession
{
    return ChatSession::create(array_merge([
        'user_id' => ($owner ?? ctUser())->id, 'title' => null, 'state' => 'active',
        'generation' => 1, 'next_sequence' => 1,
    ], $attrs))->refresh();
}

function ctGate(): TurnGate
{
    return app(TurnGate::class);
}

function ctKey(int $n = 1): string
{
    return sprintf('40000000-0000-4000-8000-%012d', $n);
}

function ctReserve(ChatSession $session, int $key = 1, string $hash = 'hash-a', string $message = 'Berapa protein tahu?'): ReserveResult
{
    return ctGate()->reserve($session, ctKey($key), $hash, ['message' => $message, 'food_id' => null, 'amount_g' => null]);
}

/** A citation exactly as AnswerRenderer builds it. */
function ctCitation(Food $food, ?int $revision = null): array
{
    return [
        'id' => 'food-1', 'food_id' => (string) $food->id, 'revision' => $revision ?? (int) $food->current_revision,
        'food_name' => $food->name, 'basis' => '100g', 'source_reference' => 'USDA FoodData Central synthetic',
        'verified_at' => '2026-11-06T02:00:00Z',
    ];
}

function ctFoodRef(Food $food, ?int $revision = null): array
{
    return ['food_id' => (string) $food->id, 'revision' => $revision ?? (int) $food->current_revision];
}

function ctResponse(string $mode = 'database'): array
{
    return ['mode' => $mode, 'retrieval_status' => 'found', 'answer' => 'Jawaban sintetis.', 'claims' => [],
        'citations' => [], 'general_knowledge_notice' => null, 'warnings' => []];
}

function ctFresh(ChatTurn|ChatSession $model): ChatTurn|ChatSession
{
    return $model->newQuery()->findOrFail($model->getKey());
}

/** Turn row inserted directly (history fixtures), bypassing the gate. */
function ctTurn(ChatSession $session, int $sequence, array $attrs = []): ChatTurn
{
    return ChatTurn::create(array_merge([
        'session_id' => $session->id, 'client_turn_id' => (string) Str::uuid(), 'sequence' => $sequence,
        'request_hash' => 'h'.$sequence, 'status' => 'completed', 'token' => Str::random(40),
        'generation' => $session->generation, 'lease_expires_at' => now()->addSeconds(35),
        'message' => "Pesan $sequence", 'response' => ctResponse(), 'citations' => [], 'food_refs' => [],
    ], $attrs));
}
