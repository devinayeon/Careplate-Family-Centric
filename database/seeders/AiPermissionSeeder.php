<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The AI features use the team's own permission names (one list, owned by RolePermissionSeeder):
 *   health_tool:use_scanner  -> POST /ai/v1/scan
 *   health_tool:use_ai_chat  -> chat and food search
 *   food:verify              -> admin approve/revoke (together with the admin role; never ahli_gizi)
 * The team seeder already grants these to admin and user. This seeder only adds what it leaves out
 * (ahli_gizi, plus the legacy roles the tests use). Run it AFTER RolePermissionSeeder: that one uses syncPermissions.
 */
class AiPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['health_tool:use_scanner', 'health_tool:use_ai_chat', 'food:verify'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // WithoutModelEvents (DatabaseSeeder) skips Spatie's cache flush: refresh before granting.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $tools = ['health_tool:use_scanner', 'health_tool:use_ai_chat'];
        $rolePermissions = [
            'admin' => [...$tools, 'food:verify'],
            'family_head' => $tools,
            'nutritionist' => $tools,
            'ahli_gizi' => $tools,
            'user' => $tools,
        ];

        foreach ($rolePermissions as $role => $grants) {
            Role::findOrCreate($role, 'web')->givePermissionTo($grants);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
