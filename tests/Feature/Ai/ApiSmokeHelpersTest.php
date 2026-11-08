<?php

require_once dirname(__DIR__, 3).'/tools/api-smoke-lib.php';

function smokeTempManifest(array $snapshots): string
{
    $path = sys_get_temp_dir().'/smoke-manifest-'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($path, json_encode(['snapshots' => $snapshots]));

    return $path;
}

test('smokePortOpen detects a listener and a free port', function () {
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    $port = (int) substr(strrchr((string) stream_socket_get_name($server, false), ':'), 1);

    expect(smokePortOpen('127.0.0.1', $port))->toBeTrue();
    fclose($server);
    expect(smokePortOpen('127.0.0.1', $port))->toBeFalse();
});

test('smokeManifestHasSource matches only the exact provider, snapshot and source id', function () {
    $path = smokeTempManifest([
        ['source_provider' => 'MANUAL', 'snapshot_version' => 'v1', 'imported_source_ids' => ['abc', 123]],
        ['source_provider' => 'USDA_FDC', 'snapshot_version' => 'v2', 'imported_source_ids' => ['zzz']],
    ]);

    expect(smokeManifestHasSource($path, 'MANUAL', 'v1', 'abc'))->toBeTrue()
        ->and(smokeManifestHasSource($path, 'MANUAL', 'v1', '123'))->toBeTrue()
        ->and(smokeManifestHasSource($path, 'MANUAL', 'v2', 'abc'))->toBeFalse()
        ->and(smokeManifestHasSource($path, 'USDA_FDC', 'v1', 'abc'))->toBeFalse()
        ->and(smokeManifestHasSource($path, 'MANUAL', 'v1', 'smoke-1'))->toBeFalse()
        ->and(smokeManifestHasSource($path.'.missing', 'MANUAL', 'v1', 'abc'))->toBeFalse();
    unlink($path);
});

test('a random smoke source triple is absent from the real manifest', function () {
    $tag = 'smoke-'.bin2hex(random_bytes(4));

    expect(smokeManifestHasSource(base_path('ai/data/snapshot.manifest.json'), 'MANUAL', $tag, $tag))->toBeFalse();
});

test('smokeLogProvesOurServer needs the banner for the exact address', function () {
    expect(smokeLogProvesOurServer("INFO Server running on [http://127.0.0.1:8001].\n", SMOKE_BASE))->toBeTrue()
        ->and(smokeLogProvesOurServer("INFO Server running on [http://127.0.0.1:8002].\n", SMOKE_BASE))->toBeFalse()
        ->and(smokeLogProvesOurServer('', SMOKE_BASE))->toBeFalse();
});

test('smokeFail throws the printable SmokeFailure type', function () {
    expect(fn () => smokeFail('x'))->toThrow(SmokeFailure::class, 'x');
});

test('smokeBannerPort reads the bound port and is null before the banner is flushed', function () {
    expect(smokeBannerPort("\n INFO Server running on [http://127.0.0.1:8001]. \n"))->toBe(8001)
        ->and(smokeBannerPort('Server running on [http://127.0.0.1:8002].'))->toBe(8002)
        ->and(smokeBannerPort(''))->toBeNull();
});

test('smokeStartDecision: late banner keeps waiting, then is ready', function () {
    // /up already answers 200 from our child, banner not flushed yet.
    expect(smokeStartDecision(true, 200, null, 1.0))->toBe('wait')
        ->and(smokeStartDecision(true, 200, null, 29.9))->toBe('wait')
        ->and(smokeStartDecision(true, 200, 8001, 1.5))->toBe('ready')
        ->and(smokeStartDecision(true, 0, null, 0.5))->toBe('wait')
        ->and(smokeStartDecision(true, 0, 8001, 0.5))->toBe('wait');
});

test('smokeStartDecision: child exited, wrong port and deadline outcomes', function () {
    expect(smokeStartDecision(false, 200, 8001, 1.0))->toBe('exited')
        ->and(smokeStartDecision(false, 0, null, 1.0))->toBe('exited')
        ->and(smokeStartDecision(true, 200, 8002, 1.0))->toBe('wrong_port')
        ->and(smokeStartDecision(true, 0, 8002, 1.0))->toBe('wrong_port')
        ->and(smokeStartDecision(true, 200, null, 30.0))->toBe('no_banner')
        ->and(smokeStartDecision(true, 0, null, 30.0))->toBe('never_up')
        ->and(smokeStartDecision(true, 0, 8001, 30.0))->toBe('never_up');
});
