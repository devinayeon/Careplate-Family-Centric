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

            // Resource-oriented permissions
            'food:read',
            'food:create',
            'food:update',
            'food:delete',
            'food:verify',
            'meal_log:read',
            'meal_log:create',
            'meal_log:update',
            'meal_log:delete',
            'article:read',
            'article:create',
            'article:update',
            'article:delete',
            'article:publish',
            'consultation:request',
            'consultation:reply',
            'consultation:close',
            'consultation:view_all',
            'family:read',
            'family:create',
            'family:join',
            'family:manage_member',
            'user:read',
            'user:update',
            'user:delete',
            'health_tool:use_scanner',
            'health_tool:use_calculator',
            'health_tool:use_ai_chat',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Hapus role legacy family_head jika ada
        $legacyRole = Role::where('name', 'family_head')->first();
        if ($legacyRole) {
            $legacyRole->delete();
        }

        // 2. Define 3 Core Roles
        // A. Admin
        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->syncPermissions(Permission::all());

        // B. Ahli Gizi (Nutritionist)
        $ahliGiziRole = Role::findOrCreate('ahli_gizi', 'web');
        $nutritionistRole = Role::findOrCreate('nutritionist', 'web');
        $nutritionistPerms = [
            'manage-nutrition-plans',
            'view-nutrition-plans',
            'give-consultation',
            'view-health-reports',
            'consultation:reply',
            'consultation:close',
            'food:read',
            'food:create',
            'article:read',
            'article:create',
            'meal_log:read',
        ];
        $ahliGiziRole->syncPermissions($nutritionistPerms);
        $nutritionistRole->syncPermissions($nutritionistPerms);

        // C. User (Customer / Pengguna Aplikasi)
        $userRole = Role::findOrCreate('user', 'web');
        $userPerms = [
            'request-consultation',
            'log-daily-meals',
            'view-health-reports',
            'meal_log:read',
            'meal_log:create',
            'meal_log:update',
            'meal_log:delete',
            'family:read',
            'family:create',
            'family:join',
            'family:manage_member',
            'food:read',
            'food:create',
            'article:read',
            'health_tool:use_scanner',
            'health_tool:use_calculator',
            'health_tool:use_ai_chat',
        ];
        $userRole->syncPermissions($userPerms);

        // 3. Default demo users
        $admin = User::firstOrCreate(
            ['email' => 'admin@careplate.test'],
            [
                'name' => 'Admin CarePlate',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles([$adminRole]);

        $nutritionist = User::firstOrCreate(
            ['email' => 'dr.gizi@careplate.test'],
            [
                'name' => 'dr. Sari Wulandari (Ahli Gizi)',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $nutritionist->syncRoles([$ahliGiziRole, $nutritionistRole]);

        \App\Models\AhliGizi::updateOrCreate(
            ['user_id' => $nutritionist->id],
            [
                'spesialisasi' => 'Gizi Keluarga',
                'nomor_str' => '1234 5678 9012 3456',
                'jam_layanan' => 'Senin sampai Sabtu, 08.00 sampai 12.00',
            ]
        );

        // User Budi Santoso (Customer yang juga bertindak sebagai kepala keluarga di data grupnya)
        $budi = User::firstOrCreate(
            ['email' => 'budi@careplate.test'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $budi->syncRoles([$userRole]);

        // User Rina Santoso
        $userRina = User::firstOrCreate(
            ['email' => 'rina@careplate.test'],
            [
                'name' => 'Rina Santoso',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $userRina->syncRoles([$userRole]);
    }
}
