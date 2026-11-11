<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $nama_keluarga
 * @property string|null $kode_keluarga
 * @property int $kepala_keluarga_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pengguna $kepalaKeluarga
 * @property-read Collection<int, Pengguna> $anggota
 */
class Keluarga extends Model
{
    use HasFactory;

    protected $table = 'keluarga';

    protected $fillable = [
        'nama_keluarga',
        'kode_keluarga',
        'kepala_keluarga_id',
    ];

    /**
     * Kepala keluarga (pemilik grup keluarga).
     */
    public function kepalaKeluarga(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'kepala_keluarga_id');
    }

    /**
     * Seluruh anggota keluarga yang terhubung.
     */
    public function anggota(): HasMany
    {
        return $this->hasMany(Pengguna::class, 'keluarga_id');
    }

    /**
     * Generate 6-karakter kode unik untuk undangan bergabung keluarga.
     */
    public static function generateKodeKeluarga(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (static::where('kode_keluarga', $code)->exists());

        return $code;
    }
}
