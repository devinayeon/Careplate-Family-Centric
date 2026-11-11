<?php

use App\Models\User;
use Database\Seeders\AiPermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

// Owner request 2026-11-11: the scan and chat screens (Figma "04 Pindai Kemasan", "05 Chat Gizi") open from the user menu.

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    $this->seed(AiPermissionSeeder::class);
});

it('sends a guest to the login page', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with(['/pindai-kemasan', '/chat-gizi']);

it('renders the scan page for a user with the scanner permission and passes the daily limit', function () {
    $user = User::factory()->create()->assignRole('user');

    $this->actingAs($user)->get('/pindai-kemasan')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('User/Pindai')
            ->where('scanLimitPerDay', 10)
            ->has('aiEnabled'),
    );
});

it('renders the chat page for a user with the chat permission', function () {
    $user = User::factory()->create()->assignRole('user');

    $this->actingAs($user)->get('/chat-gizi')->assertOk()->assertInertia(
        fn (Assert $page) => $page->component('User/ChatGizi')->has('aiEnabled'),
    );
});

it('reads the daily scan limit from config so a paid tier needs one edit', function () {
    config(['ai.quotas.scan.per_day' => 60]);
    $user = User::factory()->create()->assignRole('user');

    $this->actingAs($user)->get('/pindai-kemasan')->assertInertia(fn (Assert $page) => $page->where('scanLimitPerDay', 60));
});

it('refuses a signed-in user who lacks the permission', function (string $path) {
    $user = User::factory()->create();

    $this->actingAs($user)->get($path)->assertForbidden();
})->with(['/pindai-kemasan', '/chat-gizi']);
