<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pengguna_id
 * @property int $makanan_id
 * @property \Illuminate\Support\Carbon $tanggal_konsumsi
 * @property string $waktu_konsumsi
 * @property string|null $waktu_makan
 * @property string $jumlah_porsi
 * @property string|null $total_kalori
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Pengguna $pengguna
 * @property-read Makanan $makanan
 */
class PelacakanMakanan extends Model
{
    use HasFactory;

    protected $table = 'pelacakan_makanan';

    protected $fillable = [
        'pengguna_id',
        'makanan_id',
        'tanggal_konsumsi',
        'waktu_konsumsi',
        'waktu_makan',
        'jumlah_porsi',
        'total_kalori',
    ];

    /** Meal times in display order (Figma 03), key => label. */
    public const WAKTU_MAKAN = [
        'sarapan' => 'Sarapan',
        'makan_siang' => 'Makan Siang',
        'makan_malam' => 'Makan Malam',
        'camilan' => 'Camilan',
    ];

    /**
     * The stored meal time, or for rows written before `waktu_makan` existed, the one implied by the clock time
     * (before 11.00 breakfast, before 16.00 lunch, otherwise dinner; a snack cannot be inferred).
     */
    public function waktuMakanEfektif(): string
    {
        if ($this->waktu_makan !== null && isset(self::WAKTU_MAKAN[$this->waktu_makan])) {
            return $this->waktu_makan;
        }
        $hour = (int) substr((string) $this->waktu_konsumsi, 0, 2);

        return $hour < 11 ? 'sarapan' : ($hour < 16 ? 'makan_siang' : 'makan_malam');
    }

    /** Calories of `porsi` portions of a food (energi is per one portion = kuantitas satuan), 2 decimals. */
    public static function hitungKalori(Makanan $makanan, string $porsi): string
    {
        $energi = (string) $makanan->energi; // decimal(8,2) NOT NULL column: always a numeric string
        $porsi = is_numeric($porsi) ? $porsi : '0';

        return bcadd(bcmul($energi, $porsi, 4), '0.005', 2);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pengguna_id' => 'integer',
            'makanan_id' => 'integer',
            'tanggal_konsumsi' => 'date',
            'jumlah_porsi' => 'decimal:2',
            'total_kalori' => 'decimal:2',
        ];
    }

    /**
     * Boot model: otomatis hitung total_kalori dari makanan jika belum diisi.
     */
    protected static function booted(): void
    {
        static::creating(function (PelacakanMakanan $pelacakan) {
            // Only when no value was given: an explicit 0 (a zero-calorie food) must stay 0.
            if ($pelacakan->total_kalori === null && $pelacakan->makanan_id) {
                $makanan = Makanan::withTrashed()->find($pelacakan->makanan_id);
                if ($makanan) {
                    $pelacakan->total_kalori = self::hitungKalori($makanan, (string) ($pelacakan->jumlah_porsi ?: '1'));
                }
            }
        });
    }

    /**
     * Pengguna yang mencatat konsumsi makanan.
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'pengguna_id');
    }

    /**
     * Data makanan yang dikonsumsi.
     */
    public function makanan(): BelongsTo
    {
        return $this->belongsTo(Makanan::class, 'makanan_id');
    }

    /**
     * Scope filter catatan hari ini.
     */
    public function scopeHariIni(Builder $query): Builder
    {
        return $query->whereDate('tanggal_konsumsi', Carbon::today());
    }

    /**
     * Scope filter berdasarkan tanggal tertentu.
     */
    public function scopeTanggal(Builder $query, string $tanggal): Builder
    {
        return $query->whereDate('tanggal_konsumsi', $tanggal);
    }
}
