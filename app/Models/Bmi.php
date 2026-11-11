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
 * @property float $berat_badan
 * @property float $tinggi_badan
 * @property int $pengguna_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pengguna $pengguna
 */
class Bmi extends Model
{
    use HasFactory;

    protected $table = 'bmi';

    protected $fillable = [
        'skor',
        'keterangan',
        'berat_badan',
        'tinggi_badan',
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
            'pengguna_id' => 'integer',
        ];
    }

    /**
     * Pengguna pemilik riwayat kalkulasi BMI.
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }

    /**
     * Hitung nilai BMI berdasarkan berat badan (kg) dan tinggi badan (cm).
     */
    public static function hitungNilaiBmi(float $beratBadanKg, float $tinggiBadanCm): float
    {
        if ($tinggiBadanCm <= 0) {
            return 0.0;
        }

        $tinggiMeter = $tinggiBadanCm / 100;

        return round($beratBadanKg / ($tinggiMeter * $tinggiMeter), 2);
    }

    /**
     * Tentukan klasifikasi BMI berdasarkan standar Kemenkes RI / WHO Asia Pasifik.
     */
    public static function tentukanKategori(float $bmi): string
    {
        if ($bmi < 18.5) {
            return 'Underweight';
        }

        if ($bmi < 23.0) {
            return 'Normal';
        }

        if ($bmi < 25.0) {
            return 'Overweight';
        }

        return 'Obese';
    }
}
