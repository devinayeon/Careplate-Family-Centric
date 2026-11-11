<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $nama
 * @property float $energi
 * @property float $lemak
 * @property float $protein
 * @property float $kuantitas
 * @property string $satuan
 * @property float $karbohidrat
 * @property float $gula
 * @property float $natrium
 * @property string $sumber
 * @property string $status
 * @property int|null $pengaju_id
 * @property string $nama_pengaju
 * @property int $kategori_makanan_id
 * @property string|null $foto_gizi
 * @property string|null $foto_makanan
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\KategoriMakanan $kategoriMakanan
 * @property-read \App\Models\User|null $pengaju
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PelacakanMakanan> $pelacakanMakanan
 */
class Makanan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'makanan';

    protected $fillable = [
        'nama',
        'energi',
        'lemak',
        'protein',
        'kuantitas',
        'satuan',
        'karbohidrat',
        'gula',
        'natrium',
        'sumber',
        'status',
        'alasan_penolakan',
        'diverifikasi_oleh',
        'diverifikasi_pada',
        'pengaju_id',
        'nama_pengaju',
        'kategori_makanan_id',
        'foto_gizi',
        'foto_makanan',
    ];

    protected $appends = [
        'foto_makanan_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'energi' => 'decimal:2',
            'lemak' => 'decimal:2',
            'protein' => 'decimal:2',
            'kuantitas' => 'decimal:2',
            'karbohidrat' => 'decimal:2',
            'gula' => 'decimal:2',
            'natrium' => 'decimal:2',
            'kategori_makanan_id' => 'integer',
            'pengaju_id' => 'integer',
            'diverifikasi_oleh' => 'integer',
            'diverifikasi_pada' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Kategori makanan.
     */
    public function kategoriMakanan(): BelongsTo
    {
        return $this->belongsTo(KategoriMakanan::class, 'kategori_makanan_id');
    }

    /**
     * Pengguna yang mengajukan data makanan (jika ada).
     */
    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengaju_id');
    }

    /**
     * Admin yang memverifikasi atau menolak makanan.
     */
    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    /**
     * Log pelacakan konsumsi makanan ini.
     */
    public function pelacakanMakanan(): HasMany
    {
        return $this->hasMany(PelacakanMakanan::class, 'makanan_id');
    }

    /**
     * Accessor URL foto makanan.
     */
    public function getFotoMakananUrlAttribute(): ?string
    {
        if (! $this->foto_makanan) {
            return null;
        }

        if (Str::startsWith($this->foto_makanan, ['http://', 'https://', '/'])) {
            return $this->foto_makanan;
        }

        return asset('storage/' . $this->foto_makanan);
    }

    /**
     * Scope filter pencarian dan status.
     *
     * @param  Builder<Makanan>  $query
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query->when($filters['search'] ?? null, function (Builder $query, string $search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('sumber', 'like', "%{$search}%")
                    ->orWhere('nama_pengaju', 'like', "%{$search}%")
                    ->orWhereHas('kategoriMakanan', function ($qk) use ($search) {
                        $qk->where('kategori', 'like', "%{$search}%");
                    });
            });
        })->when($filters['status'] ?? null, function (Builder $query, string $status) {
            if ($status !== 'semua') {
                $query->where('status', $status);
            }
        });
    }

    /**
     * Scope pencarian nama makanan.
     */
    public function scopeCari(Builder $query, string $keyword): Builder
    {
        return $query->where('nama', 'like', "%{$keyword}%");
    }

    /**
     * Scope filter berdasarkan kategori.
     */
    public function scopeKategori(Builder $query, int $kategoriId): Builder
    {
        return $query->where('kategori_makanan_id', $kategoriId);
    }
}
