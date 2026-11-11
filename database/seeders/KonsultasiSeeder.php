<?php

namespace Database\Seeders;

use App\Models\AhliGizi;
use App\Models\Konsultasi;
use App\Models\PesanKonsultasi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class KonsultasiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ahliGiziRole = Role::findOrCreate('ahli_gizi', 'web');
        $nutritionistRole = Role::findOrCreate('nutritionist', 'web');
        $userRole = Role::findOrCreate('user', 'web');

        // 1. Seed beberapa Ahli Gizi Berpengalaman
        $nutritionistsData = [
            [
                'email' => 'dr.gizi@careplate.test',
                'name' => 'dr. Sari Wulandari, S.Gz, RD',
                'spesialisasi' => 'Gizi Keluarga',
                'nomor_str' => '1234 5678 9012 3456',
                'jam_layanan' => 'Senin - Sabtu, 08.00 - 16.00 WIB',
                'bio' => 'Praktisi gizi klinis berpengalaman 8 tahun dalam penanganan pola makan keluarga, gizi anak, serta pencegahan stunting.',
                'tarif_konsultasi' => 50000.00,
            ],
            [
                'email' => 'hendra.gizi@careplate.test',
                'name' => 'dr. Hendra Pratama, Sp.GK',
                'spesialisasi' => 'Gizi Klinis & Penyakit Dalam',
                'nomor_str' => '3201 8892 1104 9921',
                'jam_layanan' => 'Senin - Jumat, 13.00 - 20.00 WIB',
                'bio' => 'Spesialis gizi klinis berfokus pada manajemen nutrisi diabetes mellitus, hipertensi, hiperkolesterolemia, dan penyakit ginjal.',
                'tarif_konsultasi' => 75000.00,
            ],
            [
                'email' => 'maya.gizi@careplate.test',
                'name' => 'Maya Wulandari, S.Gz, M.Nutr',
                'spesialisasi' => 'Diet & Manajemen Berat Badan',
                'nomor_str' => '1899 4432 0019 7782',
                'jam_layanan' => 'Setiap Hari, 09.00 - 18.00 WIB',
                'bio' => 'Konsultan nutrisi dan fat loss berbasis bukti ilmiah. Membantu membangun kebiasaan makan sehat berkelanjutan tanpa rasa tersiksa.',
                'tarif_konsultasi' => 45000.00,
            ],
            [
                'email' => 'nurul.gizi@careplate.test',
                'name' => 'Nurul Aini, S.Gz',
                'spesialisasi' => 'Gizi Ibu Hamil & Menyusui',
                'nomor_str' => '4401 2291 7731 5509',
                'jam_layanan' => 'Senin - Kamis, 08.00 - 15.00 WIB',
                'bio' => 'Edukator ASI dan nutrisi 1.000 Hari Pertama Kehidupan (HPK). Berpengalaman mendampingi ibu hamil dan menyusui selama 6 tahun.',
                'tarif_konsultasi' => 50000.00,
            ],
        ];

        $nutritionistModels = [];

        foreach ($nutritionistsData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles([$ahliGiziRole, $nutritionistRole]);

            $ag = AhliGizi::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'spesialisasi' => $data['spesialisasi'],
                    'nomor_str' => $data['nomor_str'],
                    'jam_layanan' => $data['jam_layanan'],
                    'bio' => $data['bio'],
                    'tarif_konsultasi' => $data['tarif_konsultasi'],
                    'is_aktif' => true,
                ]
            );

            $nutritionistModels[] = $ag;
        }

        // 2. Client User (Rina Santoso & Budi Santoso)
        $userRina = User::firstOrCreate(
            ['email' => 'rina@careplate.test'],
            [
                'name' => 'Rina Santoso',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $userRina->syncRoles([$userRole]);

        $userBudi = User::firstOrCreate(
            ['email' => 'budi@careplate.test'],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $userBudi->syncRoles([$userRole]);

        $drSari = $nutritionistModels[0];
        $drHendra = $nutritionistModels[1];
        $drMaya = $nutritionistModels[2];

        // 3. Konsultasi 1: Status 'berlangsung' (Rina -> dr. Sari)
        $konsultasi1 = Konsultasi::firstOrCreate(
            ['nomor_konsultasi' => 'KNS-202611-0001'],
            [
                'user_id' => $userRina->id,
                'ahli_gizi_id' => $drSari->id,
                'target_tipe' => 'orang_lain',
                'nama_pasien' => 'Dina',
                'usia_pasien' => 8,
                'topik' => 'Gizi Keluarga',
                'keluhan' => 'Dina sering minta camilan manis sepulang sekolah dan susah makan sayur.',
                'data_kesehatan' => [
                    'bmi_usia' => 'Normal',
                    'energi_rata_rata' => '1.420 kkal',
                    'camilan_manis' => '2 kali per hari',
                    'sayur_tercatat' => '3 dari 7 hari',
                ],
                'status' => 'berlangsung',
                'disetujui_pada' => now()->subHours(2),
            ]
        );

        // Pesan-pesan untuk konsultasi 1
        if ($konsultasi1->pesan()->count() === 0) {
            PesanKonsultasi::create([
                'konsultasi_id' => $konsultasi1->id,
                'sender_id' => $userRina->id,
                'tipe_pesan' => 'teks',
                'pesan' => 'Halo dr. Sari, Dina sering sekali minta camilan manis setelah pulang sekolah. Bagaimana cara menggantinya dengan camilan sehat yang tetap ia sukai?',
                'is_read' => true,
                'created_at' => now()->subMinutes(80),
            ]);

            PesanKonsultasi::create([
                'konsultasi_id' => $konsultasi1->id,
                'sender_id' => $drSari->user_id,
                'tipe_pesan' => 'teks',
                'pesan' => 'Halo Bu Rina! Terima kasih sudah berkonsultasi. Saya sudah melihat catatan asupan Dina. Untuk awal, kita bisa coba buah potong manis seperti mangga atau pisang dengan selai kacang murni, atau puding chia tanpa pemanis buatan.',
                'is_read' => true,
                'created_at' => now()->subMinutes(60),
            ]);

            PesanKonsultasi::create([
                'konsultasi_id' => $konsultasi1->id,
                'sender_id' => $drSari->user_id,
                'tipe_pesan' => 'meeting',
                'pesan' => 'Jadwal tatap muka konsultasi gizi online telah dibuat.',
                'metadata' => [
                    'tanggal' => 'Jumat, 14 Nov 2026',
                    'mulai' => '16.00 WIB',
                    'durasi' => '30 mnt',
                    'link' => 'https://meet.google.com/kns-sari-rina',
                ],
                'is_read' => true,
                'created_at' => now()->subMinutes(30),
            ]);
        }

        // 4. Konsultasi 2: Status 'menunggu' (Budi -> dr. Hendra)
        Konsultasi::firstOrCreate(
            ['nomor_konsultasi' => 'KNS-202611-0002'],
            [
                'user_id' => $userBudi->id,
                'ahli_gizi_id' => $drHendra->id,
                'target_tipe' => 'diri_sendiri',
                'nama_pasien' => 'Budi Santoso',
                'usia_pasien' => 38,
                'topik' => 'Gizi Klinis & Penyakit Dalam',
                'keluhan' => 'Hasil lab terakhir menunjukkan kolesterol total 235 mg/dL. Ingin panduan pola makan rendah lemak jenuh tanpa mengurangi energi harian.',
                'data_kesehatan' => [
                    'bmi_usia' => 'Kelebihan Berat Badan (BMI 26.2)',
                    'energi_rata_rata' => '2.300 kkal',
                    'camilan_manis' => '1 kali per hari',
                    'sayur_tercatat' => '2 dari 7 hari',
                ],
                'status' => 'menunggu',
            ]
        );

        // 5. Konsultasi 3: Status 'selesai' (Rina -> dr. Maya)
        $konsultasi3 = Konsultasi::firstOrCreate(
            ['nomor_konsultasi' => 'KNS-202611-0003'],
            [
                'user_id' => $userRina->id,
                'ahli_gizi_id' => $drMaya->id,
                'target_tipe' => 'diri_sendiri',
                'nama_pasien' => 'Rina Santoso',
                'usia_pasien' => 34,
                'topik' => 'Diet & Manajemen Berat Badan',
                'keluhan' => 'Ingin menurunkan berat badan 4 kg secara bertahap pasca melahirkan anak kedua.',
                'status' => 'selesai',
                'disetujui_pada' => now()->subDays(10),
                'selesai_pada' => now()->subDays(2),
            ]
        );

        if ($konsultasi3->pesan()->count() === 0) {
            PesanKonsultasi::create([
                'konsultasi_id' => $konsultasi3->id,
                'sender_id' => $userRina->id,
                'tipe_pesan' => 'teks',
                'pesan' => 'Konsultasi meal plan mingguan sangat membantu, terima kasih banyak Mbak Maya!',
                'is_read' => true,
                'created_at' => now()->subDays(2)->subHours(3),
            ]);

            PesanKonsultasi::create([
                'konsultasi_id' => $konsultasi3->id,
                'sender_id' => $drMaya->user_id,
                'tipe_pesan' => 'teks',
                'pesan' => 'Sama-sama Bu Rina, progresnya sangat luar biasa. Sesi konsultasi resmi ini saya tandai selesai. Tetap jaga konsistensinya ya!',
                'is_read' => true,
                'created_at' => now()->subDays(2)->subHours(2),
            ]);
        }
    }
}
