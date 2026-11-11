<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Artikel extends Model
{
    use HasFactory;

    protected $table = 'artikel';

    protected $fillable = [
        'judul',
        'slug',
        'ringkasan',
        'konten',
        'kategori',
        'foto_sampul',
        'penulis_id',
        'nama_penulis',
        'status',
        'published_at',
        'views',
    ];

    protected $appends = [
        'foto_sampul_url',
        'waktu_baca',
        'tanggal_terbit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'views' => 'integer',
        ];
    }

    /**
     * Relasi ke User penulis artikel.
     */
    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penulis_id');
    }

    /**
     * Accessor untuk URL foto sampul.
     */
    public function getFotoSampulUrlAttribute(): ?string
    {
        if (! $this->foto_sampul) {
            return null;
        }

        if (Str::startsWith($this->foto_sampul, ['http://', 'https://', '/'])) {
            return $this->foto_sampul;
        }

        return asset('storage/'.$this->foto_sampul);
    }

    /**
     * Accessor estimasi waktu baca (rata-rata 200 kata per menit).
     */
    public function getWaktuBacaAttribute(): string
    {
        $rawText = trim(strip_tags((string) $this->konten));
        $wordCount = str_word_count($rawText);
        $minutes = max(1, (int) ceil($wordCount / 200));

        return "{$minutes} menit baca";
    }

    /**
     * Accessor tanggal publikasi yang terformat (contoh: 30 Okt 2026).
     */
    public function getTanggalTerbitAttribute(): string
    {
        $date = $this->published_at ?? $this->created_at;

        return $date ? $date->translatedFormat('d M Y') : '';
    }

    /**
     * Scope filter pencarian dan status.
     *
     * @param  Builder<Artikel>  $query
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query->when($filters['search'] ?? null, function (Builder $query, string $search) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('judul', 'like', "%{$search}%")
                    ->orWhere('kategori', 'like', "%{$search}%")
                    ->orWhere('nama_penulis', 'like', "%{$search}%");
            });
        })->when($filters['status'] ?? null, function (Builder $query, string $status) {
            if ($status !== 'semua') {
                $query->where('status', $status);
            }
        });
    }
}
