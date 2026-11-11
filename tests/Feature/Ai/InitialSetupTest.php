<?php

use App\Models\User;
use Database\Seeders\AiPermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('AI defaults leave generation and provider request storage disabled', function () {
    expect(config('ai.enabled'))->toBeFalse()
        ->and(config('ai.gemini.store'))->toBeFalse();
});

test('AI permission seeding is repeatable without creating user accounts', function () {
    $this->seed(AiPermissionSeeder::class);

    $permissionCount = Permission::query()->count();
    $roleCount = Role::query()->count();

    $this->seed(AiPermissionSeeder::class);

    expect(User::query()->count())->toBe(0)
        ->and(Permission::query()->count())->toBe($permissionCount)
        ->and(Role::query()->count())->toBe($roleCount);
});

test('AI permissions reserve food verification for admins', function () {
    $this->seed(AiPermissionSeeder::class);

    $admin = Role::findByName('admin', 'web');
    $familyHead = Role::findByName('family_head', 'web');
    $nutritionist = Role::findByName('nutritionist', 'web');

    expect($admin->hasPermissionTo('food:verify', 'web'))->toBeTrue()
        ->and($familyHead->hasPermissionTo('health_tool:use_ai_chat', 'web'))->toBeTrue()
        ->and($nutritionist->hasPermissionTo('health_tool:use_scanner', 'web'))->toBeTrue()
        ->and($nutritionist->hasPermissionTo('health_tool:use_ai_chat', 'web'))->toBeTrue()
        ->and($familyHead->hasPermissionTo('food:verify', 'web'))->toBeFalse()
        ->and($nutritionist->hasPermissionTo('food:verify', 'web'))->toBeFalse();
});

test('AI permission seeding preserves existing role permissions', function () {
    $existingPermission = Permission::findOrCreate('view-existing-reports', 'web');

    foreach (['admin', 'family_head', 'nutritionist'] as $role) {
        Role::findOrCreate($role, 'web')->givePermissionTo($existingPermission);
    }

    $this->seed(AiPermissionSeeder::class);
    $this->seed(AiPermissionSeeder::class);

    foreach (['admin', 'family_head', 'nutritionist'] as $role) {
        expect(Role::findByName($role, 'web')->hasPermissionTo('view-existing-reports', 'web'))->toBeTrue();
    }

    expect(User::query()->count())->toBe(0);
});
