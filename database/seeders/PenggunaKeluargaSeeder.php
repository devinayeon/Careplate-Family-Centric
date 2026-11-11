<?php

namespace Database\Seeders;

use App\Models\Bmi;
use App\Models\KebutuhanKalori;
use App\Models\Keluarga;
use App\Models\Makanan;
use App\Models\PelacakanMakanan;
use App\Models\Pengguna;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class PenggunaKeluargaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roleUser = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);

        // 1. Dapatkan atau buat akun user Budi Santoso (role: user)
        $userKepala = User::firstOrCreate(
            ['email' => 'family@careplate.test'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (method_exists($userKepala, 'hasRole') && ! $userKepala->hasRole('user')) {
            $userKepala->assignRole($roleUser);
        }

        // 2. Buat profil Pengguna
        $penggunaKepala = Pengguna::updateOrCreate(
            ['user_id' => $userKepala->id],
            [
                'jenis_kelamin' => 'Laki-laki',
                'berat_badan' => 70.00,
                'tinggi_badan' => 172.00,
                'tanggal_lahir' => Carbon::parse('1988-06-15'),
                'aktivitas_fisik' => 'Moderate',
                'bmi' => 23.66,
                'kalori' => 2200.00,
            ]
        );

        // 3. Buat Keluarga 'Keluarga Santoso'
        // Status kepala keluarga ditentukan oleh kolom relasi kepala_keluarga_id, BUKAN role RBAC!
        $keluarga = Keluarga::firstOrCreate(
            ['nama_keluarga' => 'Keluarga Santoso'],
            [
                'kode_keluarga' => 'CP8899',
                'kepala_keluarga_id' => $penggunaKepala->id,
            ]
        );

        // Update keluarga_id pada pengguna kepala
        $penggunaKepala->update(['keluarga_id' => $keluarga->id]);

        // 4. Buat Anggota Keluarga kedua (Siti Rahmawati)
        $userAnggota = User::firstOrCreate(
            ['email' => 'user@careplate.test'],
            [
                'name' => 'Siti Rahmawati',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        if (method_exists($userAnggota, 'hasRole') && ! $userAnggota->hasRole('user')) {
            $userAnggota->assignRole($roleUser);
        }

        $penggunaAnggota = Pengguna::updateOrCreate(
            ['user_id' => $userAnggota->id],
            [
                'jenis_kelamin' => 'Perempuan',
                'berat_badan' => 54.00,
                'tinggi_badan' => 160.00,
                'tanggal_lahir' => Carbon::parse('1992-09-20'),
                'aktivitas_fisik' => 'Light',
                'bmi' => 21.09,
                'kalori' => 1750.00,
                'keluarga_id' => $keluarga->id,
            ]
        );

        // 5. Histori BMI untuk Budi Santoso
        Bmi::firstOrCreate(
            [
                'pengguna_id' => $penggunaKepala->id,
                'berat_badan' => 70.00,
                'tinggi_badan' => 172.00,
            ],
            [
                'skor' => 23.66,
                'keterangan' => 'Overweight',
            ]
        );

        // 6. Histori Kebutuhan Kalori untuk Budi Santoso
        KebutuhanKalori::firstOrCreate(
            [
                'pengguna_id' => $penggunaKepala->id,
                'berat_badan' => 70.00,
                'tinggi_badan' => 172.00,
                'usia' => $penggunaKepala->usia ?? 38,
            ],
            [
                'skor' => 2200.00,
                'keterangan' => 'Maintenance',
                'jenis_kelamin' => 'Laki-laki',
                'aktivitas_fisik' => 'Moderate',
            ]
        );

        // 7. Riwayat Pelacakan Makanan hari ini
        $makanan1 = Makanan::where('nama', 'like', '%Oatmeal%')->first();
        $makanan2 = Makanan::where('nama', 'like', '%Nasi Merah%')->first();
        $makanan3 = Makanan::where('nama', 'like', '%Dada Ayam%')->first();

        if ($makanan1) {
            PelacakanMakanan::firstOrCreate([
                'pengguna_id' => $penggunaKepala->id,
                'makanan_id' => $makanan1->id,
                'tanggal_konsumsi' => Carbon::today(),
                'waktu_konsumsi' => '07:30:00',
            ], [
                'jumlah_porsi' => 1.00,
                'total_kalori' => $makanan1->energi,
            ]);
        }

        if ($makanan2 && $makanan3) {
            PelacakanMakanan::firstOrCreate([
                'pengguna_id' => $penggunaKepala->id,
                'makanan_id' => $makanan2->id,
                'tanggal_konsumsi' => Carbon::today(),
                'waktu_konsumsi' => '12:30:00',
            ], [
                'jumlah_porsi' => 1.00,
                'total_kalori' => $makanan2->energi,
            ]);

            PelacakanMakanan::firstOrCreate([
                'pengguna_id' => $penggunaKepala->id,
                'makanan_id' => $makanan3->id,
                'tanggal_konsumsi' => Carbon::today(),
                'waktu_konsumsi' => '12:30:00',
            ], [
                'jumlah_porsi' => 1.00,
                'total_kalori' => $makanan3->energi,
            ]);
        }
    }
}
