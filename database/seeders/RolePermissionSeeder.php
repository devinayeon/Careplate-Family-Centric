<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define Permissions
        $permissions = [
            // Admin management
            'manage-users',
            'manage-roles',

            // Nutrition & Health management
            'manage-nutrition-plans',
            'view-nutrition-plans',
            'give-consultation',
            'request-consultation',

            // Family & Meal tracking
            'manage-family-members',
            'log-daily-meals',
            'view-health-reports',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // 2. Define Roles and Assign Permissions
        // Admin
        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->givePermissionTo(Permission::all());

        // Nutritionist (Ahli Gizi)
        $nutritionistRole = Role::findOrCreate('nutritionist', 'web');
        $nutritionistRole->givePermissionTo([
            'manage-nutrition-plans',
            'view-nutrition-plans',
            'give-consultation',
            'view-health-reports',
        ]);

        // Family Head / User (Kepala Keluarga)
        $familyHeadRole = Role::findOrCreate('family_head', 'web');
        $familyHeadRole->givePermissionTo([
            'view-nutrition-plans',
            'request-consultation',
            'manage-family-members',
            'log-daily-meals',
            'view-health-reports',
        ]);

        // Default demo users (optional for initial development testing)
        $admin = User::firstOrCreate(
            ['email' => 'admin@careplate.test'],
            [
                'name' => 'Careplate Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles([$adminRole]);

        $nutritionist = User::firstOrCreate(
            ['email' => 'dr.gizi@careplate.test'],
            [
                'name' => 'dr. Sarah (Ahli Gizi)',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $nutritionist->syncRoles([$nutritionistRole]);

        $familyHead = User::firstOrCreate(
            ['email' => 'budi@careplate.test'],
            [
                'name' => 'Budi Santoso (Kepala Keluarga)',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $familyHead->syncRoles([$familyHeadRole]);
    }
}

