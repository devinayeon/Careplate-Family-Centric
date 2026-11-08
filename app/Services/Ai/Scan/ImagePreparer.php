<?php

namespace App\Services\Ai\Scan;

use App\Exceptions\Ai\AiException;
use finfo;
use GdImage;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Validates an uploaded label photo and re-encodes it to a clean JPEG (no metadata),
 * EXIF-oriented, at most MAX_SIDE px on the longest side. Header checks (MIME from bytes,
 * size, pixel count, memory) all run before the full decode. Works in memory only.
 */
class ImagePreparer
{
    public const MAX_SIDE = 2000;

    public const JPEG_QUALITY = 85;

    /** Headroom for the resampled copy, its rotation and GD overhead. */
    private const MEMORY_HEADROOM = 40 * 1024 * 1024;

    private const TYPES = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG];

    /**
     * @throws AiException UNSUPPORTED_MEDIA_TYPE (415), IMAGE_TOO_LARGE (413), INVALID_INPUT (422)
     */
    public function prepare(UploadedFile $file): PreparedImage
    {
        if (! $file->isValid()) {
            throw AiException::for(in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 'IMAGE_TOO_LARGE' : 'INVALID_INPUT');
        }

        $path = $file->getPathname();
        $size = @filesize($path);
        if ($size === false) {
            throw AiException::for('INVALID_INPUT');
        }
        if ($size > (int) config('ai.scan.max_bytes')) {
            throw AiException::for('IMAGE_TOO_LARGE');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        $allowed = array_intersect((array) config('ai.scan.mime_types'), array_keys(self::TYPES));
        if (! is_string($mime) || ! in_array($mime, $allowed, true)) {
            throw AiException::for('UNSUPPORTED_MEDIA_TYPE');
        }

        [$width, $height] = $this->inspectHeader($path, $mime);

        try {
            return $this->reencode((string) file_get_contents($path), $mime, $width, $height);
        } catch (AiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw AiException::for('INVALID_INPUT', previous: $e);
        }
    }

    /**
     * Reads dimensions from the header only and enforces the pixel and memory budgets.
     *
     * @return array{0: int, 1: int}
     */
    private function inspectHeader(string $path, string $mime): array
    {
        $info = @getimagesize($path);
        if ($info === false || $info[2] !== self::TYPES[$mime] || $info[0] < 1 || $info[1] < 1) {
            throw AiException::for('INVALID_INPUT');
        }

        [$width, $height] = $info;
        if ($width * $height > (int) config('ai.scan.max_pixels') || ! $this->fitsMemory($width, $height)) {
            throw AiException::for('IMAGE_TOO_LARGE');
        }

        return [$width, $height];
    }

    /** GD holds a decoded truecolor image at about 4 bytes per pixel. */
    private function fitsMemory(int $width, int $height): bool
    {
        $limit = $this->memoryLimit();

        return $limit === -1 || $width * $height * 4 + self::MEMORY_HEADROOM <= $limit - memory_get_usage();
    }

    private function memoryLimit(): int
    {
        $raw = trim((string) ini_get('memory_limit'));
        if ($raw === '' || $raw === '-1') {
            return -1;
        }
        $n = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }

    private function reencode(string $bytes, string $mime, int $width, int $height): PreparedImage
    {
        $source = @imagecreatefromstring($bytes);
        if (! $source instanceof GdImage) {
            throw AiException::for('INVALID_INPUT');
        }

        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $targetW = max(1, (int) round($width * $scale));
        $targetH = max(1, (int) round($height * $scale));

        // New truecolor canvas filled white: flattens PNG transparency and drops every metadata block.
        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        unset($source);

        if ($mime === 'image/jpeg') {
            $canvas = $this->orient($canvas, JpegOrientation::read($bytes));
        }

        ob_start();
        $ok = imagejpeg($canvas, null, self::JPEG_QUALITY);
        $jpeg = (string) ob_get_clean();
        if (! $ok || $jpeg === '') {
            throw AiException::for('INVALID_INPUT');
        }

        return new PreparedImage($jpeg, 'image/jpeg', imagesx($canvas), imagesy($canvas));
    }

    /** Applies EXIF orientation 2-8 (1 = unchanged). imagerotate turns counter-clockwise. */
    private function orient(GdImage $img, int $orientation): GdImage
    {
        $white = (int) imagecolorallocate($img, 255, 255, 255);
        $rotate = fn (GdImage $i, int $ccw) => imagerotate($i, $ccw, $white) ?: $i;
        $flipH = function (GdImage $i): GdImage {
            imageflip($i, IMG_FLIP_HORIZONTAL);

            return $i;
        };

        return match ($orientation) {
            2 => $flipH($img),
            3 => $rotate($img, 180),
            4 => $this->flipVertical($img),
            5 => $flipH($rotate($img, -90)),
            6 => $rotate($img, -90),
            7 => $flipH($rotate($img, 90)),
            8 => $rotate($img, 90),
            default => $img,
        };
    }

    private function flipVertical(GdImage $img): GdImage
    {
        imageflip($img, IMG_FLIP_VERTICAL);

        return $img;
    }
}
