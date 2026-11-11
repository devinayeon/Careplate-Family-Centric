<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class KategoriArtikel extends Model
{
    use HasFactory;

    protected $table = 'kategori_artikel';

    protected $fillable = [
        'nama',
        'slug',
        'deskripsi',
    ];

    /**
     * Relasi ke artikel yang menggunakan nama kategori ini.
     */
    public function artikels(): HasMany
    {
        return $this->hasMany(Artikel::class, 'kategori', 'nama');
    }

    /**
     * Boot method to auto-generate slug.
     */
    protected static function booted(): void
    {
        static::saving(function (KategoriArtikel $kategori) {
            if (empty($kategori->slug)) {
                $kategori->slug = Str::slug($kategori->nama);
            }
        });
    }
}
