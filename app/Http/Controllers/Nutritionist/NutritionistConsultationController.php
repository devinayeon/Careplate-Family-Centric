<?php

namespace App\Http\Controllers\Nutritionist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NutritionistConsultationController extends Controller
{
    /**
     * Tampilkan workspace konsultasi interaktif ahli gizi.
     */
    public function index(Request $request): Response
    {
        $activeClient = [
            'id' => 1,
            'inisial' => 'RS',
            'nama' => 'Rina Santoso',
            'subjudul' => 'Dina, 8 th • Gizi anak',
            'klien_info' => [
                'untuk' => 'Dina, 8 tahun',
                'topik' => 'Gizi anak',
                'diajukan' => '3 Nov, 19.30',
                'keluhan' => 'Dina sering minta camilan manis sepulang sekolah dan susah makan sayur.',
                'data_dibagikan' => [
                    'bmi_usia' => 'Normal',
                    'energi_rata_rata' => '1.420 kkal',
                    'camilan_manis' => '2 kali per hari',
                    'sayur_tercatat' => '3 dari 7 hari',
                ],
            ],
            'messages' => [
                [
                    'id' => 1,
                    'sender' => 'client',
                    'sender_name' => 'Rina Santoso',
                    'time' => '19.42',
                    'content' => 'Dina sering minta camilan manis sepulang sekolah. Bagaimana menggantinya?',
                ],
                [
                    'id' => 2,
                    'sender' => 'nutritionist',
                    'sender_name' => 'dr. Sari Wulandari',
                    'time' => '19.42',
                    'content' => 'Terima kasih, Bu Rina. Saya sudah lihat catatan 7 hari Dina. Camilan manisnya rata-rata 2 kali sehari.',
                ],
            ],
        ];

        $daftarKlien = [
            [
                'id' => 1,
                'inisial' => 'RS',
                'nama' => 'Rina Santoso',
                'subjudul' => 'Gizi anak • Dina, 8 th',
                'waktu' => '19.42',
                'unread' => true,
                'status' => 'aktif',
            ],
            [
                'id' => 2,
                'inisial' => 'HP',
                'nama' => 'Hendra P.',
                'subjudul' => 'Penyakit tertentu',
                'waktu' => 'Kemarin',
                'unread' => false,
                'status' => 'aktif',
            ],
            [
                'id' => 3,
                'inisial' => 'MW',
                'nama' => 'Maya W.',
                'subjudul' => 'Berat badan',
                'waktu' => '31 Okt',
                'unread' => false,
                'status' => 'aktif',
            ],
        ];

        return Inertia::render('Nutritionist/Konsultasi', [
            'tabs' => [
                'baru' => 1,
                'aktif' => 3,
                'selesai' => 12,
            ],
            'klien_list' => $daftarKlien,
            'active_client' => $activeClient,
        ]);
    }
}
