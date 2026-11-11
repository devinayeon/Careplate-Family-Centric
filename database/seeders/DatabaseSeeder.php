<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            AiPermissionSeeder::class, // after RolePermissionSeeder: that one syncs (overwrites) role permissions
            KategoriMakananSeeder::class,
            MakananSeeder::class,
        ]);

        // Demo accounts (well-known password) and sample articles: never seed them in production.
        if (! app()->environment('production')) {
            $this->call([
                PenggunaKeluargaSeeder::class,
                ArtikelSeeder::class,
            ]);
        }
    }
}
