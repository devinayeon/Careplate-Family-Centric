<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property float $skor
 * @property string $keterangan
 * @property string $jenis_kelamin
 * @property float $berat_badan
 * @property float $tinggi_badan
 * @property int $usia
 * @property string $aktivitas_fisik
 * @property int $pengguna_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pengguna $pengguna
 */
class KebutuhanKalori extends Model
{
    use HasFactory;

    protected $table = 'kebutuhan_kalori';

    protected $fillable = [
        'skor',
        'keterangan',
        'jenis_kelamin',
        'berat_badan',
        'tinggi_badan',
        'usia',
        'aktivitas_fisik',
        'pengguna_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'skor' => 'decimal:2',
            'berat_badan' => 'decimal:2',
            'tinggi_badan' => 'decimal:2',
            'usia' => 'integer',
            'pengguna_id' => 'integer',
        ];
    }

    /**
     * Pengguna pemilik riwayat kebutuhan kalori.
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }

    /**
     * Hitung BMR (Basal Metabolic Rate) menggunakan rumus Harris-Benedict (Revised).
     */
    public static function hitungBmr(string $jenisKelamin, float $beratBadanKg, float $tinggiBadanCm, int $usia): float
    {
        $jk = strtolower(trim($jenisKelamin));
        if ($jk === 'perempuan' || $jk === 'female' || $jk === 'wanita') {
            return 655.1 + (9.563 * $beratBadanKg) + (1.850 * $tinggiBadanCm) - (4.676 * $usia);
        }

        // Laki-laki / Male
        return 66.5 + (13.75 * $beratBadanKg) + (5.003 * $tinggiBadanCm) - (6.75 * $usia);
    }

    /**
     * Dapatkan koefisien pengali aktivitas fisik.
     */
    public static function getMultiplierAktivitas(string $aktivitasFisik): float
    {
        return match (strtolower(trim($aktivitasFisik))) {
            'sedentary', 'jarang olahraga', 'tidak aktif' => 1.2,
            'light', 'ringan', 'olahraga ringan' => 1.375,
            'moderate', 'sedang', 'olahraga sedang' => 1.55,
            'active', 'berat', 'olahraga rutin' => 1.725,
            'very active', 'sangat berat', 'atlet' => 1.9,
            default => 1.2,
        };
    }

    /**
     * Hitung Total Daily Energy Expenditure (TDEE).
     */
    public static function hitungTdee(string $jenisKelamin, float $beratBadanKg, float $tinggiBadanCm, int $usia, string $aktivitasFisik): float
    {
        $bmr = self::hitungBmr($jenisKelamin, $beratBadanKg, $tinggiBadanCm, $usia);
        $multiplier = self::getMultiplierAktivitas($aktivitasFisik);

        return round($bmr * $multiplier, 2);
    }
}
