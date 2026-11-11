<?php

use App\Models\User;
use Database\Seeders\AiPermissionSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolePermissionSeeder;

function asProduction(): void
{
    app()->detectEnvironment(fn () => 'production');
}

function seedForced(string $class): void
{
    test()->artisan('db:seed', ['--class' => $class, '--force' => true])->assertSuccessful();
}

it('registers new users with the role `user` that holds the AI permissions', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(AiPermissionSeeder::class);

    $this->post('/register', [
        'name' => 'New Person', 'email' => 'new@example.com',
        'password' => 'password', 'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $user = User::where('email', 'new@example.com')->firstOrFail();
    expect($user->hasRole('user'))->toBeTrue()
        ->and($user->hasPermissionTo('health_tool:use_scanner'))->toBeTrue()
        ->and($user->hasPermissionTo('food:verify'))->toBeFalse();
});

it('never creates demo accounts with the default password in production', function () {
    asProduction();

    seedForced(RolePermissionSeeder::class);
    seedForced(AiPermissionSeeder::class);

    expect(User::query()->count())->toBe(0);
});

it('creates the demo accounts outside production', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(User::where('email', 'admin@careplate.test')->exists())->toBeTrue();
});

it('DatabaseSeeder in production seeds no demo accounts', function () {
    asProduction();

    seedForced(DatabaseSeeder::class);

    expect(User::where('email', 'like', '%@careplate.test')->count())->toBe(0);
});

it('refuses to approve the catalog by script in production without an explicit flag', function () {
    asProduction();

    $this->artisan('ai:seed-catalog')->assertFailed();
    $this->artisan('ai:seed-catalog', ['--allow-script-approval' => true])->assertSuccessful();
});
