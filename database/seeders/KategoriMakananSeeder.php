<?php

namespace Database\Seeders;

use App\Models\KategoriMakanan;
use Illuminate\Database\Seeder;

class KategoriMakananSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kategoriList = [
            'Sarapan',
            'Makan Siang',
            'Makan Malam',
            'Camilan & Snack',
            'Minuman Sehat',
            'Buah & Sayur',
        ];

        foreach ($kategoriList as $kategori) {
            KategoriMakanan::firstOrCreate([
                'kategori' => $kategori,
            ]);
        }
    }
}
