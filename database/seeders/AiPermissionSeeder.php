<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AiPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'scan-nutrition',
            'nutrition-chat',
            'submit-food',
            'verify-food',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // WithoutModelEvents (DatabaseSeeder) skips Spatie's cache flush: refresh before granting.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $rolePermissions = [
            'admin' => $permissions,
            'family_head' => ['scan-nutrition', 'nutrition-chat', 'submit-food'],
            'nutritionist' => ['scan-nutrition', 'nutrition-chat'],
            // Roles of the team's RolePermissionSeeder (it uses syncPermissions, so run this seeder AFTER it).
            'ahli_gizi' => ['scan-nutrition', 'nutrition-chat'],
            'user' => ['scan-nutrition', 'nutrition-chat', 'submit-food'],
        ];

        foreach ($rolePermissions as $role => $grants) {
            Role::findOrCreate($role, 'web')->givePermissionTo($grants);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
