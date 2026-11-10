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

        $rolePermissions = [
            'admin' => $permissions,
            'family_head' => ['scan-nutrition', 'nutrition-chat', 'submit-food'],
            'nutritionist' => ['scan-nutrition', 'nutrition-chat'],
        ];

        foreach ($rolePermissions as $role => $grants) {
            Role::findOrCreate($role, 'web')->givePermissionTo($grants);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
