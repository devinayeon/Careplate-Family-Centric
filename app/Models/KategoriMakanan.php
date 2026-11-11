<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $kategori
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Makanan> $makanan
 */
class KategoriMakanan extends Model
{
    use HasFactory;

    protected $table = 'kategori_makanan';

    protected $fillable = [
        'kategori',
    ];

    /**
     * Seluruh data makanan dalam kategori ini.
     */
    public function makanan(): HasMany
    {
        return $this->hasMany(Makanan::class, 'kategori_makanan_id');
    }
}
