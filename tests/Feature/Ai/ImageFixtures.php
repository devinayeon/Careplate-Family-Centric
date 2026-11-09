<?php

use App\Exceptions\Ai\AiException;
use App\Services\Ai\Scan\ImagePreparer;
use Illuminate\Http\UploadedFile;

/** In-test GD fixtures: nothing binary is committed. */
function gdEncode(GdImage $img, string $type = 'jpeg'): string
{
    ob_start();
    $type === 'png' ? imagepng($img) : ($type === 'webp' ? imagewebp($img) : ($type === 'gif' ? imagegif($img) : imagejpeg($img, null, 95)));

    return (string) ob_get_clean();
}

/** Four coloured quadrants: TL red, TR green, BL blue, BR yellow. */
function quadrantImage(int $w = 60, int $h = 40): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    $half = [intdiv($w, 2), intdiv($h, 2)];
    foreach ([[0, 0, 255, 0, 0], [$half[0], 0, 0, 255, 0], [0, $half[1], 0, 0, 255], [$half[0], $half[1], 255, 255, 0]] as [$x, $y, $r, $g, $b]) {
        imagefilledrectangle($img, $x, $y, $x + $half[0] - 1, $y + $half[1] - 1, imagecolorallocate($img, $r, $g, $b));
    }

    return $img;
}

function solidImage(int $w, int $h, array $rgb = [120, 160, 90]): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, ...$rgb));

    return $img;
}

/** Insert an EXIF APP1 segment holding only the Orientation tag right after SOI. */
function withExifOrientation(string $jpeg, int $orientation, bool $bigEndian = true): string
{
    $pack = fn (int $n, string $be, string $le) => pack($bigEndian ? $be : $le, $n);
    $tiff = ($bigEndian ? 'MM' : 'II').$pack(42, 'n', 'v').$pack(8, 'N', 'V')
        .$pack(1, 'n', 'v')                                            // one IFD0 entry
        .$pack(0x0112, 'n', 'v').$pack(3, 'n', 'v').$pack(1, 'N', 'V') // Orientation, SHORT, count 1
        .$pack($orientation, 'n', 'v').pack('n', 0)                    // value + padding
        .pack('N', 0);                                                 // no next IFD
    $payload = "Exif\0\0".$tiff;

    return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
}

/** Classify the colour at a point as R, G, B or Y. */
function colorAt(string $jpeg, int $x, int $y): string
{
    $img = imagecreatefromstring($jpeg);
    $c = imagecolorat($img, $x, $y);
    $bits = [($c >> 16 & 255) > 150, ($c >> 8 & 255) > 150, ($c & 255) > 150];

    return match ($bits) {
        [true, false, false] => 'R',
        [false, true, false] => 'G',
        [false, false, true] => 'B',
        [true, true, false] => 'Y',
        default => 'W',
    };
}

/** Colours at the four corner regions of the image, in TL, TR, BL, BR order. */
function cornerColors(string $jpeg): string
{
    [$w, $h] = getimagesizefromstring($jpeg);
    $dx = intdiv($w, 4);
    $dy = intdiv($h, 4);

    return colorAt($jpeg, $dx, $dy).colorAt($jpeg, $w - $dx, $dy).colorAt($jpeg, $dx, $h - $dy).colorAt($jpeg, $w - $dx, $h - $dy);
}

/** A PNG that only carries a header: valid signature, IHDR (with CRC) and IEND. */
function pngHeaderOnly(int $w, int $h): string
{
    $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

    return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0)).$chunk('IEND', '');
}

function upload(string $content, string $name = 'label.jpg'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $content);
}

function prepareCode(UploadedFile $file): string
{
    try {
        (new ImagePreparer)->prepare($file);
    } catch (AiException $e) {
        return $e->errorCode;
    }

    return 'NO_EXCEPTION';
}
