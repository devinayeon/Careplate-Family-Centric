<?php

use Database\Seeders\AiPermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

it('gives the team roles their AI permissions when the AI seeder runs after the role seeder', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(AiPermissionSeeder::class);

    $user = Role::findByName('user', 'web');
    $expert = Role::findByName('ahli_gizi', 'web');
    $admin = Role::findByName('admin', 'web');

    expect($user->hasPermissionTo('scan-nutrition', 'web'))->toBeTrue()
        ->and($user->hasPermissionTo('nutrition-chat', 'web'))->toBeTrue()
        ->and($user->hasPermissionTo('submit-food', 'web'))->toBeTrue()
        ->and($user->hasPermissionTo('verify-food', 'web'))->toBeFalse()
        ->and($expert->hasPermissionTo('nutrition-chat', 'web'))->toBeTrue()
        ->and($expert->hasPermissionTo('submit-food', 'web'))->toBeFalse()
        ->and($admin->hasPermissionTo('verify-food', 'web'))->toBeTrue();
});

it('loses the AI permissions if the role seeder runs last (documents the required order)', function () {
    $this->seed(AiPermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    expect(Role::findByName('user', 'web')->hasPermissionTo('scan-nutrition', 'web'))->toBeFalse();
});

it('DatabaseSeeder runs the AI seeder after the role seeder and keeps it repeatable', function () {
    $this->seed();
    $this->seed();

    expect(Role::findByName('user', 'web')->hasPermissionTo('nutrition-chat', 'web'))->toBeTrue();
});
