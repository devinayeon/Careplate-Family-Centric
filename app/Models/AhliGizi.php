<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $spesialisasi
 * @property string|null $nomor_str
 * @property string|null $jam_layanan
 * @property string|null $bio
 * @property float|null $tarif_konsultasi
 * @property bool $is_aktif
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User $user
 */
class AhliGizi extends Model
{
    use HasFactory;

    protected $table = 'ahli_gizi';

    protected $fillable = [
        'user_id',
        'spesialisasi',
        'nomor_str',
        'jam_layanan',
        'bio',
        'tarif_konsultasi',
        'is_aktif',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'tarif_konsultasi' => 'decimal:2',
            'is_aktif' => 'boolean',
        ];
    }

    /**
     * Akun user pemilik profil ahli gizi.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
