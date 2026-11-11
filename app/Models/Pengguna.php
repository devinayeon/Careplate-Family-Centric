<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $jenis_kelamin
 * @property float|null $berat_badan
 * @property float|null $tinggi_badan
 * @property \Illuminate\Support\Carbon|null $tanggal_lahir
 * @property string|null $aktivitas_fisik
 * @property float|null $bmi
 * @property float|null $kalori
 * @property int|null $keluarga_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Keluarga|null $keluarga
 * @property-read \App\Models\Keluarga|null $keluargaDipimpin
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Bmi> $bmiHistori
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\KebutuhanKalori> $kebutuhanKaloriHistori
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PelacakanMakanan> $pelacakanMakanan
 * @property-read int|null $usia
 */
class Pengguna extends Model
{
    use HasFactory;

    protected $table = 'pengguna';

    protected $fillable = [
        'user_id',
        'jenis_kelamin',
        'berat_badan',
        'tinggi_badan',
        'tanggal_lahir',
        'aktivitas_fisik',
        'bmi',
        'kalori',
        'keluarga_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'berat_badan' => 'decimal:2',
            'tinggi_badan' => 'decimal:2',
            'tanggal_lahir' => 'date',
            'bmi' => 'decimal:2',
            'kalori' => 'decimal:2',
            'keluarga_id' => 'integer',
        ];
    }

    /**
     * Akun user terkait.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Grup keluarga tempat pengguna bergabung.
     */
    public function keluarga(): BelongsTo
    {
        return $this->belongsTo(Keluarga::class, 'keluarga_id');
    }

    /**
     * Keluarga yang dipimpin oleh pengguna (sebagai kepala keluarga).
     */
    public function keluargaDipimpin(): HasOne
    {
        return $this->hasOne(Keluarga::class, 'kepala_keluarga_id');
    }

    /**
     * Riwayat kalkulasi BMI.
     */
    public function bmiHistori(): HasMany
    {
        return $this->hasMany(Bmi::class, 'pengguna_id');
    }

    /**
     * Riwayat kalkulasi kebutuhan kalori harian.
     */
    public function kebutuhanKaloriHistori(): HasMany
    {
        return $this->hasMany(KebutuhanKalori::class, 'pengguna_id');
    }

    /**
     * Log pencatatan makanan yang dikonsumsi.
     */
    public function pelacakanMakanan(): HasMany
    {
        return $this->hasMany(PelacakanMakanan::class, 'pengguna_id');
    }

    /**
     * Hitung usia berdasarkan tanggal lahir.
     */
    public function getUsiaAttribute(): ?int
    {
        return $this->tanggal_lahir ? Carbon::parse($this->tanggal_lahir)->age : null;
    }

    /**
     * Cek apakah pengguna saat ini bertindak sebagai kepala keluarga.
     */
    public function isKepalaKeluarga(): bool
    {
        return ! is_null($this->keluarga_id) && $this->keluargaDipimpin()->exists();
    }
}
