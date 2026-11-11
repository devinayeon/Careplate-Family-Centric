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
 * @property float $jumlah_porsi
 * @property float $total_kalori
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
        'jumlah_porsi',
        'total_kalori',
    ];

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
            if (empty($pelacakan->total_kalori) && $pelacakan->makanan_id) {
                $makanan = Makanan::find($pelacakan->makanan_id);
                if ($makanan) {
                    $pelacakan->total_kalori = round((float) $makanan->energi * (float) ($pelacakan->jumlah_porsi ?: 1), 2);
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
