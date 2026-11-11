<?php

namespace Database\Seeders;

use App\Models\KategoriMakanan;
use App\Models\Makanan;
use Illuminate\Database\Seeder;

class MakananSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kategoriSarapan = KategoriMakanan::where('kategori', 'Sarapan')->first();
        $kategoriSiang = KategoriMakanan::where('kategori', 'Makan Siang')->first();
        $kategoriMalam = KategoriMakanan::where('kategori', 'Makan Malam')->first();
        $kategoriSnack = KategoriMakanan::where('kategori', 'Camilan & Snack')->first();
        $kategoriMinuman = KategoriMakanan::where('kategori', 'Minuman Sehat')->first();
        $kategoriBuah = KategoriMakanan::where('kategori', 'Buah & Sayur')->first();

        $daftarMakanan = [
            // Sarapan
            [
                'nama' => 'Oatmeal Buah Berry & Chia Seeds',
                'energi' => 210.00,
                'lemak' => 4.50,
                'protein' => 7.00,
                'kuantitas' => 200.00,
                'satuan' => 'gram',
                'karbohidrat' => 38.00,
                'kategori_makanan_id' => $kategoriSarapan?->id ?? 1,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],
            [
                'nama' => 'Telur Rebus Matang',
                'energi' => 78.00,
                'lemak' => 5.30,
                'protein' => 6.30,
                'kuantitas' => 50.00,
                'satuan' => 'butir',
                'karbohidrat' => 0.60,
                'kategori_makanan_id' => $kategoriSarapan?->id ?? 1,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],
            [
                'nama' => 'Roti Gandum Panggang Alpukat',
                'energi' => 185.00,
                'lemak' => 8.20,
                'protein' => 5.50,
                'kuantitas' => 1.00,
                'satuan' => 'porsi',
                'karbohidrat' => 24.00,
                'kategori_makanan_id' => $kategoriSarapan?->id ?? 1,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],

            // Makan Siang
            [
                'nama' => 'Nasi Putih',
                'energi' => 130.00,
                'lemak' => 0.30,
                'protein' => 2.70,
                'kuantitas' => 100.00,
                'satuan' => 'gram',
                'karbohidrat' => 28.20,
                'kategori_makanan_id' => $kategoriSiang?->id ?? 2,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],
            [
                'nama' => 'Nasi Merah Organik',
                'energi' => 110.00,
                'lemak' => 0.90,
                'protein' => 2.60,
                'kuantitas' => 100.00,
                'satuan' => 'gram',
                'karbohidrat' => 23.00,
                'kategori_makanan_id' => $kategoriSiang?->id ?? 2,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],
            [
                'nama' => 'Dada Ayam Panggang Herbal',
                'energi' => 165.00,
                'lemak' => 3.60,
                'protein' => 31.00,
                'kuantitas' => 100.00,
                'satuan' => 'gram',
                'karbohidrat' => 0.00,
                'kategori_makanan_id' => $kategoriSiang?->id ?? 2,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],
            [
                'nama' => 'Tempe Bacem Panggang',
                'energi' => 125.00,
                'lemak' => 5.50,
                'protein' => 10.20,
                'kuantitas' => 50.00,
                'satuan' => 'potong',
                'karbohidrat' => 9.80,
                'kategori_makanan_id' => $kategoriSiang?->id ?? 2,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],
            [
                'nama' => 'Sayur Bening Bayam Jagung Manis',
                'energi' => 48.00,
                'lemak' => 0.60,
                'protein' => 2.80,
                'kuantitas' => 150.00,
                'satuan' => 'mangkok',
                'karbohidrat' => 8.50,
                'kategori_makanan_id' => $kategoriSiang?->id ?? 2,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],

            // Makan Malam
            [
                'nama' => 'Ikan Kembung Bakar Kunyit',
                'energi' => 167.00,
                'lemak' => 9.80,
                'protein' => 19.40,
                'kuantitas' => 100.00,
                'satuan' => 'ekor',
                'karbohidrat' => 0.00,
                'kategori_makanan_id' => $kategoriMalam?->id ?? 3,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],
            [
                'nama' => 'Tahu Kukus Isi Sayuran',
                'energi' => 88.00,
                'lemak' => 4.20,
                'protein' => 8.50,
                'kuantitas' => 100.00,
                'satuan' => 'potong',
                'karbohidrat' => 4.10,
                'kategori_makanan_id' => $kategoriMalam?->id ?? 3,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],
            [
                'nama' => 'Sayur Sop Wortel & Brokoli Bening',
                'energi' => 52.00,
                'lemak' => 0.80,
                'protein' => 2.50,
                'kuantitas' => 150.00,
                'satuan' => 'mangkok',
                'karbohidrat' => 9.20,
                'kategori_makanan_id' => $kategoriMalam?->id ?? 3,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],

            // Camilan & Snack
            [
                'nama' => 'Edamame Rebus',
                'energi' => 122.00,
                'lemak' => 5.20,
                'protein' => 11.00,
                'kuantitas' => 100.00,
                'satuan' => 'gram',
                'karbohidrat' => 9.90,
                'kategori_makanan_id' => $kategoriSnack?->id ?? 4,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],
            [
                'nama' => 'Kacang Almond Panggang',
                'energi' => 164.00,
                'lemak' => 14.20,
                'protein' => 6.00,
                'kuantitas' => 28.00,
                'satuan' => 'gram',
                'karbohidrat' => 6.10,
                'kategori_makanan_id' => $kategoriSnack?->id ?? 4,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],

            // Minuman Sehat
            [
                'nama' => 'Air Kelapa Muda Murni',
                'energi' => 46.00,
                'lemak' => 0.50,
                'protein' => 1.70,
                'kuantitas' => 250.00,
                'satuan' => 'ml',
                'karbohidrat' => 8.90,
                'kategori_makanan_id' => $kategoriMinuman?->id ?? 5,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],
            [
                'nama' => 'Jus Wortel Apel Jeruk (Cold Pressed)',
                'energi' => 95.00,
                'lemak' => 0.40,
                'protein' => 1.50,
                'kuantitas' => 250.00,
                'satuan' => 'ml',
                'karbohidrat' => 22.00,
                'kategori_makanan_id' => $kategoriMinuman?->id ?? 5,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],

            // Buah & Sayur
            [
                'nama' => 'Pisang Cavendish',
                'energi' => 105.00,
                'lemak' => 0.30,
                'protein' => 1.30,
                'kuantitas' => 1.00,
                'satuan' => 'buah',
                'karbohidrat' => 27.00,
                'kategori_makanan_id' => $kategoriBuah?->id ?? 6,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],
            [
                'nama' => 'Apel Fuji Segar',
                'energi' => 78.00,
                'lemak' => 0.20,
                'protein' => 0.40,
                'kuantitas' => 1.00,
                'satuan' => 'buah',
                'karbohidrat' => 21.00,
                'kategori_makanan_id' => $kategoriBuah?->id ?? 6,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],
            [
                'nama' => 'Pepaya Potong Segar',
                'energi' => 62.00,
                'lemak' => 0.40,
                'protein' => 0.90,
                'kuantitas' => 150.00,
                'satuan' => 'gram',
                'karbohidrat' => 15.60,
                'kategori_makanan_id' => $kategoriBuah?->id ?? 6,
                'foto_makanan' => null,
                'foto_gizi' => null,
            ],
        ];

        foreach ($daftarMakanan as $item) {
            Makanan::firstOrCreate(
                ['nama' => $item['nama']],
                $item
            );
        }
    }
}
