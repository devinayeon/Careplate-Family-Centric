<?php

namespace App\Services\Ai\Scan;

/**
 * A clean, re-encoded scan image held in memory only (never persisted or logged).
 */
final readonly class PreparedImage
{
    public function __construct(
        public string $bytes,
        public string $mime,
        public int $width,
        public int $height,
    ) {}
}
