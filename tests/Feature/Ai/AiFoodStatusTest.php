<?php

use App\Models\KategoriMakanan;
use App\Models\Makanan;
use App\Models\User;
use App\Services\Ai\Foods\AiFoodStatus;
use Database\Seeders\AiPermissionSeeder;
use Database\Seeders\RolePermissionSeeder;

// Owner request 2026-11-11 (plan phase 4): the admin sees whether a verified food is used by the AI chat, and why not.

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    $this->seed(AiPermissionSeeder::class);
    $this->category = KategoriMakanan::query()->create(['kategori' => 'Uji']);
});

function asFood(array $o = []): Makanan
{
    return Makanan::query()->create($o + [
        'nama' => 'Tahu Sintetis', 'energi' => '80', 'lemak' => '4.8', 'protein' => '8', 'kuantitas' => '100', 'satuan' => 'g',
        'karbohidrat' => '1.9', 'gula' => '0', 'natrium' => '0', 'sumber' => 'Uji', 'status' => 'terverifikasi',
        'kategori_makanan_id' => test()->category->id,
    ]);
}

it('says ready for a verified food the chat can use', function () {
    $m = asFood();

    expect(AiFoodStatus::forMany(collect([$m]))[$m->id])->toMatchArray(['state' => 'ready', 'label' => 'Dipakai Chat Gizi']);
});

it('explains why a verified food is not used and what to fill in', function () {
    $portion = asFood(['nama' => 'Soto', 'satuan' => 'porsi']);
    $zero = asFood(['nama' => 'Nol', 'kuantitas' => '0']);

    $status = AiFoodStatus::forMany(collect([$portion, $zero]));

    expect($status[$portion->id]['state'])->toBe('blocked')->and($status[$portion->id]['label'])->toContain('berat per')
        ->and($status[$zero->id]['state'])->toBe('blocked');
});

it('turns ready once the admin adds the weight per unit', function () {
    $m = asFood(['satuan' => 'porsi']);
    $m->update(['berat_per_satuan_gram' => '150']);

    expect(AiFoodStatus::forMany(collect([$m->fresh()]))[$m->id]['state'])->toBe('ready');
});

it('has no status for a food that is not verified', function () {
    $m = asFood(['status' => 'menunggu']);

    expect(AiFoodStatus::forMany(collect([$m]))[$m->id])->toBeNull();
});

it('reports ready only while the AI food is really published (not when the sync is off)', function () {
    config(['ai.sync_makanan' => false]);
    $m = asFood();

    expect(AiFoodStatus::forMany(collect([$m]))[$m->id])->toMatchArray(['state' => 'blocked'])
        ->and(AiFoodStatus::forMany(collect([$m]))[$m->id]['label'])->toContain('ai:sync-makanan');
});

it('shows the status on the admin food table and the verification list', function () {
    asFood(['nama' => 'Ada Status']);
    $admin = User::factory()->create()->assignRole('admin');

    $row = $this->actingAs($admin)->get('/admin/data-makanan')->assertOk()->viewData('page')['props']['foods']['data'][0];

    expect($row['ai_status']['state'])->toBe('ready');
});

// Owner request 2026-11-11 (Figma 13.1 "Sinkronisasi Chat Gizi"): the dashboard panel shows the real state and "Coba lagi" really retries.
it('summarises the sync: usable, waiting for a weight, and failed (eligible but not published)', function () {
    asFood(['nama' => 'Sudah Dipakai']);
    asFood(['nama' => 'Butuh Berat', 'satuan' => 'porsi']);
    config(['ai.sync_makanan' => false]);
    asFood(['nama' => 'Gagal Sinkron']);

    $summary = AiFoodStatus::summary();

    expect($summary['ready'])->toBe(1)->and($summary['needs_weight'])->toBe(1)->and($summary['failed'])->toBe(1)
        ->and($summary['failed_names'])->toBe(['Gagal Sinkron'])->and($summary['last_sync'])->not->toBeNull();
});

it('shows the real summary on the admin dashboard', function () {
    config(['ai.sync_makanan' => false]);
    asFood(['nama' => 'Gagal Sinkron']);
    $admin = User::factory()->create()->assignRole('admin');

    $ai = $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->viewData('page')['props']['sinkronisasi_ai'];

    expect($ai['failed'])->toBe(1)->and($ai['failed_names'])->toBe(['Gagal Sinkron']);
});

it('the retry publishes what was missing, and only an admin may call it', function () {
    config(['ai.sync_makanan' => false]);
    $m = asFood(['nama' => 'Gagal Sinkron']);
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs(User::factory()->create()->assignRole('user'))->post('/admin/sinkronisasi-ai')->assertForbidden();
    expect(AiFoodStatus::summary()['failed'])->toBe(1);

    $this->actingAs($admin)->post('/admin/sinkronisasi-ai')->assertRedirect();

    expect(AiFoodStatus::summary()['failed'])->toBe(0)->and(AiFoodStatus::summary()['ready'])->toBe(1);
});

it('shows the real waiting queue and latest submissions on the dashboard', function () {
    asFood(['nama' => 'Baru Diajukan', 'status' => 'menunggu', 'nama_pengaju' => 'Rina']);
    $admin = User::factory()->create()->assignRole('admin');

    $props = $this->actingAs($admin)->get('/admin/dashboard')->viewData('page')['props'];

    expect($props['antrean_verifikasi']['jumlah'])->toBe(1)
        ->and($props['pengajuan_terbaru'][0]['nama'])->toBe('Baru Diajukan')->and($props['pengajuan_terbaru'][0]['pengaju'])->toBe('Rina');
});
