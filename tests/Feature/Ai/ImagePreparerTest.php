<?php

use App\Services\Ai\Scan\ImagePreparer;
use App\Services\Ai\Scan\PreparedImage;
use Illuminate\Http\UploadedFile;

require_once __DIR__.'/ImageFixtures.php';

function prepare(string $content, string $name = 'label.jpg'): PreparedImage
{
    return (new ImagePreparer)->prepare(upload($content, $name));
}

it('re-encodes a JPEG to a clean JPEG', function () {
    $out = prepare(gdEncode(solidImage(300, 200)));

    expect($out)->toBeInstanceOf(PreparedImage::class)
        ->and($out->mime)->toBe('image/jpeg')
        ->and([$out->width, $out->height])->toBe([300, 200])
        ->and(substr($out->bytes, 0, 3))->toBe("\xFF\xD8\xFF")
        ->and(getimagesizefromstring($out->bytes)['mime'])->toBe('image/jpeg');
});

it('converts PNG input to JPEG and flattens transparency onto white', function () {
    $png = imagecreatetruecolor(40, 40);
    imagealphablending($png, false);
    imagesavealpha($png, true);
    imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));

    $out = prepare(gdEncode($png, 'png'), 'label.png');

    expect($out->mime)->toBe('image/jpeg')
        ->and(colorAt($out->bytes, 20, 20))->toBe('W');
    $c = imagecolorat(imagecreatefromstring($out->bytes), 20, 20);
    expect($c >> 16 & 255)->toBeGreaterThan(240)->and($c & 255)->toBeGreaterThan(240);
});

it('strips metadata including EXIF from the re-encoded JPEG', function () {
    $tagged = withExifOrientation(gdEncode(solidImage(60, 40)), 1);
    expect(str_contains($tagged, 'Exif'))->toBeTrue();

    $out = prepare($tagged);

    expect(str_contains($out->bytes, 'Exif'))->toBeFalse()
        ->and(str_contains($out->bytes, "\xFF\xE1"))->toBeFalse();
});

it('applies every EXIF orientation to the pixels', function (int $orientation, string $corners, int $w, int $h) {
    $out = prepare(withExifOrientation(gdEncode(quadrantImage(60, 40)), $orientation));

    expect(cornerColors($out->bytes))->toBe($corners)
        ->and([$out->width, $out->height])->toBe([$w, $h])
        ->and(getimagesizefromstring($out->bytes)[0])->toBe($w);
})->with([
    'normal' => [1, 'RGBY', 60, 40],
    'mirror horizontal' => [2, 'GRYB', 60, 40],
    'rotate 180' => [3, 'YBGR', 60, 40],
    'mirror vertical' => [4, 'BYRG', 60, 40],
    'transpose' => [5, 'RBGY', 40, 60],
    'rotate 90 cw' => [6, 'BRYG', 40, 60],
    'transverse' => [7, 'YGBR', 40, 60],
    'rotate 90 ccw' => [8, 'GYRB', 40, 60],
]);

it('reads little-endian EXIF too and ignores nonsense orientation values', function () {
    $jpeg = gdEncode(quadrantImage(60, 40));

    expect(cornerColors(prepare(withExifOrientation($jpeg, 6, false))->bytes))->toBe('BRYG')
        ->and(cornerColors(prepare(withExifOrientation($jpeg, 9))->bytes))->toBe('RGBY')
        ->and(cornerColors(prepare(withExifOrientation($jpeg, 0))->bytes))->toBe('RGBY');
});

it('downscales so the longest side is at most 2000 px and never upscales', function () {
    $big = prepare(gdEncode(solidImage(3000, 1500)));
    $tall = prepare(gdEncode(solidImage(1200, 4000)));
    $small = prepare(gdEncode(solidImage(500, 300)));

    expect([$big->width, $big->height])->toBe([2000, 1000])
        ->and([$tall->width, $tall->height])->toBe([600, 2000])
        ->and([$small->width, $small->height])->toBe([500, 300]);
});

it('rejects content that is not JPEG or PNG with UNSUPPORTED_MEDIA_TYPE, whatever the file name', function (string $content, string $name) {
    expect(prepareCode(upload($content, $name)))->toBe('UNSUPPORTED_MEDIA_TYPE');
})->with([
    'plain text named jpg' => ['hello world', 'label.jpg'],
    'svg named jpg' => ['<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>', 'label.jpg'],
    'svg named png' => ['<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'label.png'],
    'php named jpg' => ['<?php echo 1;', 'label.jpg'],
    'empty file' => ['', 'label.jpg'],
    'html named png' => ['<html><body>x</body></html>', 'label.png'],
    'pdf' => ["%PDF-1.4\n%fake", 'label.jpg'],
]);

it('rejects GIF and WebP by their bytes', function () {
    expect(prepareCode(upload(gdEncode(solidImage(10, 10), 'gif'), 'label.jpg')))->toBe('UNSUPPORTED_MEDIA_TYPE')
        ->and(prepareCode(upload(gdEncode(solidImage(10, 10), 'webp'), 'label.png')))->toBe('UNSUPPORTED_MEDIA_TYPE');
});

it('rejects an upload larger than ai.scan.max_bytes with IMAGE_TOO_LARGE before decoding', function () {
    $jpeg = gdEncode(solidImage(200, 200));
    config(['ai.scan.max_bytes' => strlen($jpeg) - 1]);
    expect(prepareCode(upload($jpeg)))->toBe('IMAGE_TOO_LARGE');

    config(['ai.scan.max_bytes' => strlen($jpeg)]);
    expect(prepareCode(upload($jpeg)))->toBe('NO_EXCEPTION');
});

it('rejects a pixel bomb from its header, without decoding', function () {
    expect(prepareCode(upload(pngHeaderOnly(20000, 20000), 'label.png')))->toBe('IMAGE_TOO_LARGE');

    config(['ai.scan.max_pixels' => 5000]);
    expect(prepareCode(upload(gdEncode(solidImage(100, 100)))))->toBe('IMAGE_TOO_LARGE');

    config(['ai.scan.max_pixels' => 10000]);
    expect(prepareCode(upload(gdEncode(solidImage(100, 100)))))->toBe('NO_EXCEPTION');
});

it('refuses an image that would not fit the remaining PHP memory', function () {
    $previous = ini_get('memory_limit');
    ini_set('memory_limit', (string) (memory_get_usage(true) + 20 * 1024 * 1024));

    try {
        $code = prepareCode(upload(pngHeaderOnly(4000, 3000), 'label.png'));
    } finally {
        ini_set('memory_limit', $previous);
    }

    expect($code)->toBe('IMAGE_TOO_LARGE');
});

it('rejects undecodable images with INVALID_INPUT', function (string $content, string $name) {
    expect(prepareCode(upload($content, $name)))->toBe('INVALID_INPUT');
})->with([
    'jpeg signature then garbage' => ["\xFF\xD8\xFF\xE0".str_repeat('x', 200), 'label.jpg'],
    'png header without image data' => [pngHeaderOnly(10, 10), 'label.png'],
    'zero-size png header' => [pngHeaderOnly(0, 0), 'label.png'],
]);

it('maps a failed upload to the right error', function () {
    $path = tempnam(sys_get_temp_dir(), 'up');
    file_put_contents($path, 'x');
    try {
        $tooBig = new UploadedFile($path, 'a.jpg', null, UPLOAD_ERR_INI_SIZE, true);
        $partial = new UploadedFile($path, 'a.jpg', null, UPLOAD_ERR_PARTIAL, true);
        expect(prepareCode($tooBig))->toBe('IMAGE_TOO_LARGE')
            ->and(prepareCode($partial))->toBe('INVALID_INPUT');
    } finally {
        @unlink($path);
    }
});
