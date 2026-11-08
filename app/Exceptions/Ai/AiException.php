<?php

namespace App\Exceptions\Ai;

use Illuminate\Http\JsonResponse;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Single error type for the /ai/v1 surface. Renders the contract envelope:
 * {"error":{"code","message","retryable"},"request_id"}.
 */
class AiException extends RuntimeException
{
    /** @var array<string, array{0:int,1:string,2:bool}> code => [http status, Indonesian message, retryable] */
    private const CATALOG = [
        'UNAUTHENTICATED' => [401, 'Silakan masuk terlebih dahulu.', false],
        'CSRF_MISMATCH' => [419, 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.', false],
        'FORBIDDEN' => [403, 'Anda tidak memiliki akses ke fitur ini.', false],
        'NOT_FOUND' => [404, 'Data tidak ditemukan.', false],
        'IDEMPOTENCY_CONFLICT' => [409, 'Permintaan dengan kunci yang sama berisi data berbeda.', false],
        'TURN_IN_PROGRESS' => [409, 'Pesan ini masih diproses.', true],
        'SESSION_BUSY' => [409, 'Sesi sedang memproses pesan lain. Coba lagi sebentar.', true],
        'STALE_REVISION' => [409, 'Data sudah berubah. Muat ulang lalu coba lagi.', false],
        // A cited food was revoked or re-revised while the answer was being prepared; the turn is closed failed.
        'KNOWLEDGE_CHANGED' => [409, 'Data pangan berubah saat jawaban disiapkan. Kirim ulang pertanyaan sebagai pesan baru.', true],
        'IMAGE_TOO_LARGE' => [413, 'Ukuran foto terlalu besar. Perkecil foto lalu coba lagi.', false],
        'UNSUPPORTED_MEDIA_TYPE' => [415, 'Format foto tidak didukung. Gunakan JPEG atau PNG.', false],
        'INVALID_INPUT' => [422, 'Data yang dikirim tidak valid.', false],
        'AMOUNT_CONFLICT' => [422, 'Jumlah porsi pada pesan tidak konsisten.', false],
        'OCR_UNREADABLE' => [422, 'Label gizi tidak terbaca. Ambil foto ulang yang lebih jelas.', false],
        'RATE_LIMITED' => [429, 'Terlalu banyak permintaan. Coba lagi nanti.', true],
        'INTERNAL' => [500, 'Terjadi kesalahan pada server.', false],
        'AI_PROVIDER_ERROR' => [502, 'Layanan AI sedang bermasalah. Coba lagi nanti.', true],
        'AI_OUTPUT_INVALID' => [502, 'Jawaban layanan AI tidak dapat dipakai. Coba lagi.', true],
        // Lock contention outside chat (SQLite busy, MySQL lock wait timeout/deadlock). Nothing was written; the same call may be retried.
        'DB_BUSY' => [503, 'Server sedang sibuk menyimpan data. Coba lagi sebentar.', true],
        'KNOWLEDGE_UNAVAILABLE' => [503, 'Data pangan belum tersedia. Coba lagi nanti.', true],
        'AI_DISABLED' => [503, 'Fitur AI sedang tidak aktif.', false],
        'POLICY_CONFIG_INVALID' => [503, 'Konfigurasi layanan AI belum valid.', false],
        'AI_TIMEOUT' => [504, 'Layanan AI belum merespons. Coba lagi nanti.', true],
    ];

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status,
        string $message,
        public readonly bool $retryable = false,
        public readonly array $headers = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public static function for(string $code, ?string $message = null, ?bool $retryable = null, array $headers = [], ?Throwable $previous = null): self
    {
        [$status, $defaultMessage, $defaultRetryable] = self::CATALOG[$code] ?? self::CATALOG['INTERNAL'];
        if (! isset(self::CATALOG[$code])) {
            $code = 'INTERNAL';
        }

        return new self($code, $status, $message ?? $defaultMessage, $retryable ?? $defaultRetryable, $headers, $previous);
    }

    /**
     * True for lock contention, where nothing was committed and the same call may be retried: SQLite "database
     * (table) is locked" (BEGIN IMMEDIATE / busy_timeout, matched by message), MySQL lock wait timeout (driver
     * code 1205), deadlock (1213) or any SQLSTATE 40001 (matched by errorInfo). Walks the previous chain, because
     * Laravel rethrows contention inside a nested transaction as a DeadlockException around the QueryException.
     */
    public static function isLockContention(Throwable $e): bool
    {
        for ($error = $e; $error !== null; $error = $error->getPrevious()) {
            if (! $error instanceof PDOException) {
                continue;
            }
            $info = $error->errorInfo;
            if (is_array($info) && (($info[0] ?? null) === '40001' || in_array($info[1] ?? null, [1205, 1213], true))) {
                return true;
            }
            if (str_contains($error->getMessage(), 'database is locked') || str_contains($error->getMessage(), 'database table is locked')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{error: array{code: string, message: string, retryable: bool}, request_id: string}
     */
    public function envelope(string $requestId): array
    {
        return [
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'retryable' => $this->retryable,
            ],
            'request_id' => $requestId,
        ];
    }

    public function toResponse(string $requestId): JsonResponse
    {
        return response()->json($this->envelope($requestId), $this->status, $this->headers + [
            'Cache-Control' => 'no-store, private',
            'X-Request-Id' => $requestId,
        ]);
    }
}
