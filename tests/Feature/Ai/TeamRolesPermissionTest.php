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

    expect($user->hasPermissionTo('health_tool:use_scanner', 'web'))->toBeTrue()
        ->and($user->hasPermissionTo('health_tool:use_ai_chat', 'web'))->toBeTrue()
        ->and($user->hasPermissionTo('food:verify', 'web'))->toBeFalse()
        ->and($expert->hasPermissionTo('health_tool:use_ai_chat', 'web'))->toBeTrue()
        ->and($expert->hasPermissionTo('food:verify', 'web'))->toBeFalse()
        ->and($admin->hasPermissionTo('food:verify', 'web'))->toBeTrue();
});

it('ahli_gizi loses its AI permissions if the role seeder runs last (documents the required order)', function () {
    $this->seed(AiPermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    expect(Role::findByName('ahli_gizi', 'web')->hasPermissionTo('health_tool:use_scanner', 'web'))->toBeFalse()
        ->and(Role::findByName('user', 'web')->hasPermissionTo('health_tool:use_scanner', 'web'))->toBeTrue();
});

it('DatabaseSeeder runs the AI seeder after the role seeder and keeps it repeatable', function () {
    $this->seed();
    $this->seed();

    expect(Role::findByName('user', 'web')->hasPermissionTo('health_tool:use_ai_chat', 'web'))->toBeTrue();
});
