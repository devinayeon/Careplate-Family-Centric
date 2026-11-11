<?php

namespace Database\Seeders;

use App\Models\Artikel;
use App\Models\KategoriArtikel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ArtikelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::role('admin')->first() ?? User::first();

        $defaultCategories = [
            'Gizi Keluarga' => 'Panduan pola makan seimbang dan nutrisi untuk keluarga.',
            'Gizi Anak' => 'Tips nutrisi penting untuk tumbuh kembang anak dan bekal sehat.',
            'Label Kemasan' => 'Edukasi membaca informasi nilai gizi dan takaran saji.',
            'Tips Sehat' => 'Kebiasaan hidup sehat, aktivitas fisik, dan hidrasi.',
            'Pola Makan' => 'Strategi pengaturan waktu dan porsi makan harian.',
            'Resep Sehat' => 'Ide kreasi masakan lezat, bergizi, dan mudah dibuat.',
        ];

        foreach ($defaultCategories as $nama => $deskripsi) {
            KategoriArtikel::firstOrCreate(
                ['nama' => $nama],
                ['slug' => Str::slug($nama), 'deskripsi' => $deskripsi]
            );
        }

        $sampleArticles = [
            [
                'judul' => 'Satu meja, satu piring: Cara makan bersama tanpa drama',
                'slug' => 'satu-meja-satu-piring-cara-makan-bersama-tanpa-drama',
                'ringkasan' => 'Makan bersama keluarga dapat meningkatkan keintiman dan membiasakan anak makan gizi seimbang tanpa paksaan.',
                'konten' => '<h2>Membiasakan Kebiasaan Makan Positif di Rumah</h2><p>Makan bersama di satu meja bukan hanya soal mengisi energi tubuh, melainkan ruang interaksi paling hangat bagi seluruh anggota keluarga. Memulai tradisi satu meja makan memberikan dampak positif bagi kebiasaan gizi anak jangka panjang.</p><blockquote>"Ketika orang tua menikmati sayuran dan buah, anak-anak secara alami akan penasaran dan ingin mencoba tanpa paksaan."</blockquote><h3>3 Langkah Mudah Memulai:</h3><ul><li><strong>Jadwalkan minimal satu kali sehari:</strong> Bisa saat sarapan atau makan malam santai.</li><li><strong>Bebas gawai (gadget-free):</strong> Jauhkan ponsel dari meja makan selama 20-30 menit.</li><li><strong>Sajikan menu variatif:</strong> Berikan pilihan lauk berprotein dan sayuran berwarna cerah.</li></ul>',
                'kategori' => 'Gizi Keluarga',
                'foto_sampul' => 'https://images.unsplash.com/photo-1547592180-85f173990554?auto=format&fit=crop&w=800&q=80',
                'nama_penulis' => 'Tim Redaksi',
                'status' => 'terbit',
                'published_at' => now()->subDays(2),
                'views' => 142,
            ],
            [
                'judul' => 'Bekal sekolah yang mengenyangkan tanpa lonjakan gula',
                'slug' => 'bekal-sekolah-yang-mengenyangkan-tanpa-lonjakan-gula',
                'ringkasan' => 'Ide menu bekal anak sekolah kaya serat dan protein yang menjaga fokus belajar tanpa rasa kantuk.',
                'konten' => '<h2>Mengapa Anak Cepat Mengantuk di Kelas?</h2><p>Lonjakan gula darah drastis akibat jajanan tinggi glukosa sering membuat anak lemas dan hilang konsentrasi di tengah jam pelajaran. Solusinya adalah menyusun kotak bekal dengan kombinasi karbohidrat kompleks, serat, dan protein sehat.</p><h3>Inspirasi Kotak Bekal Cerdas:</h3><ol><li>Nasi kepal beras merah atau ubi jalar kukus.</li><li>Telur dadar gulung bayam atau fillet ayam panggang kecap.</li><li>Potongan buah segar seperti apel, pir, atau melon.</li></ol>',
                'kategori' => 'Gizi Anak',
                'foto_sampul' => 'https://images.unsplash.com/photo-1563805042-7684c019e1cb?auto=format&fit=crop&w=800&q=80',
                'nama_penulis' => 'Tim Redaksi',
                'status' => 'terbit',
                'published_at' => now()->subDays(4),
                'views' => 89,
            ],
            [
                'judul' => 'Membaca label: per sajian vs per kemasan',
                'slug' => 'membaca-label-per-sajian-vs-per-kemasan',
                'ringkasan' => 'Jangan terkecoh angka kalori kecil! Pelajari cara tepat membedakan takaran saji dan total kalori kemasan makanan.',
                'konten' => '<h2>Trik Tersembunyi di Balik Tabel Informasi Nilai Gizi</h2><p>Banyak konsumen mengira angka kalori di depan kemasan adalah untuk keseluruhan isi bungkus makanan ringan. Padahal, seringkali itu hanya mewakili satu takaran saji (serving size).</p><blockquote>Jika satu bungkus keripik berisi 4 takaran saji, dan tiap sajian mengandung 150 kkal, total kalori yang Anda konsumsi saat menghabiskannya adalah 600 kkal!</blockquote>',
                'kategori' => 'Label Kemasan',
                'foto_sampul' => 'https://images.unsplash.com/photo-1579113800032-c38bd7635818?auto=format&fit=crop&w=800&q=80',
                'nama_penulis' => 'Tim Redaksi',
                'status' => 'terbit',
                'published_at' => now()->subDays(8),
                'views' => 230,
            ],
            [
                'judul' => 'Mengenal "Hidden Salt" pada bumbu dapur instan',
                'slug' => 'mengenal-hidden-salt-pada-bumbu-dapur-instan',
                'ringkasan' => 'Kenali natrium tersembunyi yang sering tidak terasa asin di lidah namun berpotensi memicu hipertensi.',
                'konten' => '<h2>Garam Tidak Selalu Terasa Asin</h2><p>Natrium dalam makanan olahan sering tersembunyi di balik penguat rasa MSG, pengawet natrium benzoat, dan baking powder kue. Penting bagi keluarga untuk membatasi asupan natrium harian di bawah 2.000 mg (setara 1 sendok teh garam).</p>',
                'kategori' => 'Tips Sehat',
                'foto_sampul' => 'https://images.unsplash.com/photo-1596040033229-a9821ebd058d?auto=format&fit=crop&w=800&q=80',
                'nama_penulis' => 'Tim Redaksi',
                'status' => 'draf',
                'published_at' => null,
                'views' => 0,
            ],
        ];

        foreach ($sampleArticles as $data) {
            Artikel::updateOrCreate(
                ['slug' => $data['slug']],
                array_merge($data, ['penulis_id' => $admin?->id])
            );
        }
    }
}
