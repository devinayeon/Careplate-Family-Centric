<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nama
 * @property float $energi
 * @property float $lemak
 * @property float $protein
 * @property float $kuantitas
 * @property string $satuan
 * @property float $karbohidrat
 * @property int $kategori_makanan_id
 * @property string|null $foto_gizi
 * @property string|null $foto_makanan
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\KategoriMakanan $kategoriMakanan
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PelacakanMakanan> $pelacakanMakanan
 */
class Makanan extends Model
{
    use HasFactory;

    protected $table = 'makanan';

    protected $fillable = [
        'nama',
        'energi',
        'lemak',
        'protein',
        'kuantitas',
        'satuan',
        'karbohidrat',
        'kategori_makanan_id',
        'foto_gizi',
        'foto_makanan',
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
            'kategori_makanan_id' => 'integer',
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
     * Log pelacakan konsumsi makanan ini.
     */
    public function pelacakanMakanan(): HasMany
    {
        return $this->hasMany(PelacakanMakanan::class, 'makanan_id');
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
