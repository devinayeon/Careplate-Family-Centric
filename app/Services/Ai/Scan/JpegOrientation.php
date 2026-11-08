<?php

namespace App\Services\Ai\Scan;

/**
 * Reads the EXIF Orientation tag (1-8) from JPEG bytes without the exif extension.
 * Anything malformed or out of range yields 1 (no transform).
 */
final class JpegOrientation
{
    public static function read(string $jpeg): int
    {
        $len = strlen($jpeg);
        if ($len < 4 || ! str_starts_with($jpeg, "\xFF\xD8")) {
            return 1;
        }

        $pos = 2;
        while ($pos + 4 <= $len && $jpeg[$pos] === "\xFF") {
            $marker = ord($jpeg[$pos + 1]);
            if ($marker === 0xFF) {            // fill byte
                $pos++;

                continue;
            }
            if ($marker === 0xDA || $marker === 0xD9) {   // image data or end: no EXIF further on
                return 1;
            }
            $size = self::int('n', substr($jpeg, $pos + 2, 2));
            if ($size < 2) {
                return 1;
            }
            if ($marker === 0xE1 && substr($jpeg, $pos + 4, 6) === "Exif\0\0") {
                return self::fromTiff(substr($jpeg, $pos + 10, $size - 8));
            }
            $pos += 2 + $size;
        }

        return 1;
    }

    /** Unpack one unsigned integer; callers bounds-check first, so 0 only occurs on truncated data. */
    private static function int(string $format, string $data): int
    {
        $r = unpack($format, $data);

        return $r === false ? 0 : $r[1];
    }

    private static function fromTiff(string $tiff): int
    {
        if (strlen($tiff) < 8) {
            return 1;
        }
        $big = match (substr($tiff, 0, 2)) {
            'MM' => true,
            'II' => false,
            default => null,
        };
        if ($big === null) {
            return 1;
        }
        $short = fn (int $at) => strlen($tiff) >= $at + 2 ? self::int($big ? 'n' : 'v', substr($tiff, $at, 2)) : null;
        $long = fn (int $at) => strlen($tiff) >= $at + 4 ? self::int($big ? 'N' : 'V', substr($tiff, $at, 4)) : null;

        $ifd = $long(4);
        $count = $ifd === null ? null : $short($ifd);
        if ($short(2) !== 42 || $count === null) {
            return 1;
        }

        for ($i = 0; $i < $count; $i++) {
            $entry = $ifd + 2 + $i * 12;
            if ($short($entry) === 0x0112) {
                $value = $short($entry + 8);

                return $short($entry + 2) === 3 && $value !== null && $value >= 1 && $value <= 8 ? $value : 1;
            }
        }

        return 1;
    }
}
