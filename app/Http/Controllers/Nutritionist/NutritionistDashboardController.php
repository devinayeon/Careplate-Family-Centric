<?php

namespace App\Http\Controllers\Nutritionist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NutritionistDashboardController extends Controller
{
    /**
     * Tampilkan halaman Dashboard Ikhtisar Ahli Gizi.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Nutritionist/Dashboard', [
            'stats' => [
                'klien_aktif' => 14,
                'konsultasi_hari_ini' => 5,
                'menunggu_respon' => 3,
                'rating_kepuasan' => '4.9/5.0',
            ],
            'jadwal_hari_ini' => [
                [
                    'id' => 1,
                    'klien_nama' => 'Rina Santoso',
                    'topik' => 'Gizi Anak & Pengganti Camilan',
                    'jam' => '10:00 - 10:30 WIB',
                    'status' => 'Berlangsung',
                ],
                [
                    'id' => 2,
                    'klien_nama' => 'Hendra Pratama',
                    'topik' => 'Diet Penurunan Kolesterol',
                    'jam' => '14:00 - 14:30 WIB',
                    'status' => 'Mendatang',
                ],
                [
                    'id' => 3,
                    'klien_nama' => 'Maya Wulandari',
                    'topik' => 'Meal Plan Ibu Menyusui',
                    'jam' => '16:00 - 16:30 WIB',
                    'status' => 'Mendatang',
                ],
            ],
            'antrean_konsultasi' => [
                [
                    'id' => 1,
                    'nama' => 'Rina Santoso',
                    'subjudul' => 'Dina, 8 th • Gizi anak',
                    'pesan_terakhir' => 'Dina sering minta camilan manis sepulang sekolah. Bagaimana menggantinya?',
                    'waktu' => '19.42',
                    'unread' => true,
                ],
                [
                    'id' => 2,
                    'nama' => 'Hendra P.',
                    'subjudul' => 'Penyakit tertentu',
                    'pesan_terakhir' => 'Apakah aman mengonsumsi telur rebus setiap hari?',
                    'waktu' => 'Kemarin',
                    'unread' => false,
                ],
                [
                    'id' => 3,
                    'nama' => 'Maya W.',
                    'subjudul' => 'Berat badan',
                    'pesan_terakhir' => 'Target penurunan berat badan minggu ini tercapai.',
                    'waktu' => '31 Okt',
                    'unread' => false,
                ],
            ],
        ]);
    }
}
