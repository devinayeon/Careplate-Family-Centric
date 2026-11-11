<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $konsultasi_id
 * @property int $sender_id
 * @property string $tipe_pesan
 * @property string $pesan
 * @property array<string, mixed>|null $metadata
 * @property bool $is_read
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Konsultasi $konsultasi
 * @property-read User $sender
 */
class PesanKonsultasi extends Model
{
    use HasFactory;

    protected $table = 'pesan_konsultasi';

    protected $fillable = [
        'konsultasi_id',
        'sender_id',
        'tipe_pesan',
        'pesan',
        'metadata',
        'is_read',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'konsultasi_id' => 'integer',
            'sender_id' => 'integer',
            'metadata' => 'array',
            'is_read' => 'boolean',
        ];
    }

    /**
     * Relasi ke sesi konsultasi induk.
     */
    public function konsultasi(): BelongsTo
    {
        return $this->belongsTo(Konsultasi::class, 'konsultasi_id');
    }

    /**
     * Relasi ke user pengirim pesan.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
