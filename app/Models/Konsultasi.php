<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nomor_konsultasi
 * @property int $user_id
 * @property int $ahli_gizi_id
 * @property string $target_tipe
 * @property string $nama_pasien
 * @property int $usia_pasien
 * @property string $topik
 * @property string $keluhan
 * @property array<string, mixed>|null $data_kesehatan
 * @property string $status
 * @property string|null $alasan_penolakan
 * @property Carbon|null $disetujui_pada
 * @property Carbon|null $selesai_pada
 * @property Carbon|null $ditolak_pada
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read AhliGizi $ahliGizi
 */
class Konsultasi extends Model
{
    use HasFactory;

    protected $table = 'konsultasi';

    protected $fillable = [
        'nomor_konsultasi',
        'user_id',
        'ahli_gizi_id',
        'target_tipe',
        'nama_pasien',
        'usia_pasien',
        'topik',
        'keluhan',
        'data_kesehatan',
        'status',
        'alasan_penolakan',
        'disetujui_pada',
        'selesai_pada',
        'ditolak_pada',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'ahli_gizi_id' => 'integer',
            'usia_pasien' => 'integer',
            'data_kesehatan' => 'array',
            'disetujui_pada' => 'datetime',
            'selesai_pada' => 'datetime',
            'ditolak_pada' => 'datetime',
        ];
    }

    /**
     * Relasi ke Pengguna (klien) yang mengajukan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi ke profil Ahli Gizi yang dituju.
     */
    public function ahliGizi(): BelongsTo
    {
        return $this->belongsTo(AhliGizi::class, 'ahli_gizi_id');
    }

    /**
     * Relasi ke pesan-pesan percakapan konsultasi.
     */
    public function pesan(): HasMany
    {
        return $this->hasMany(PesanKonsultasi::class, 'konsultasi_id')->orderBy('created_at', 'asc');
    }

    /**
     * Menghasilkan nomor konsultasi baru secara otomatis.
     */
    public static function generateNomor(): string
    {
        $prefix = 'KNS-' . date('Ym') . '-';
        $latest = static::where('nomor_konsultasi', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('nomor_konsultasi');

        if ($latest) {
            $lastNumber = (int) substr($latest, -4);
            $nextNumber = str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '0001';
        }

        return $prefix . $nextNumber;
    }
}
