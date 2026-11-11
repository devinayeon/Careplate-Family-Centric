<?php

use App\Models\Food;
use App\Models\KategoriMakanan;
use App\Models\Makanan;
use App\Models\User;
use Database\Seeders\AiPermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

// Owner request 2026-11-11 (Figma 07 Data Makanan): users browse verified foods, submit new ones, and follow the review.
// A verified gram food then reaches the AI chat by itself (MakananObserver).

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    $this->seed(AiPermissionSeeder::class);
    $this->category = KategoriMakanan::query()->create(['kategori' => 'Lauk Nabati']);
    $this->user = User::factory()->create(['name' => 'Rina Santoso'])->assignRole('user');
});

function ufFood(array $o = []): Makanan
{
    return Makanan::query()->create($o + [
        'nama' => 'Tahu Sintetis', 'energi' => '80', 'lemak' => '4.8', 'protein' => '8', 'kuantitas' => '100', 'satuan' => 'g',
        'karbohidrat' => '1.9', 'gula' => '0', 'natrium' => '0', 'sumber' => 'Uji', 'status' => 'terverifikasi',
        'kategori_makanan_id' => test()->category->id,
    ]);
}

function ufBody(array $o = []): array
{
    return $o + [
        'nama' => 'Oseng Tempe Kecap', 'kategori_makanan_id' => test()->category->id, 'kuantitas' => '75', 'satuan' => 'g',
        'energi' => '186', 'protein' => '11', 'karbohidrat' => '14', 'lemak' => '9', 'gula' => '6', 'natrium' => '420',
        'sumber' => 'Label kemasan',
    ];
}

it('sends a guest to login and refuses a user without the food permissions', function () {
    $this->get('/data-makanan')->assertRedirect('/login');

    $nobody = User::factory()->create();
    $this->actingAs($nobody)->get('/data-makanan')->assertForbidden();
    $this->actingAs($nobody)->get('/data-makanan/ajukan')->assertForbidden();
    $this->actingAs($nobody)->post('/data-makanan/ajukan', ufBody())->assertForbidden();
});

it('lists only verified foods and filters by search and category', function () {
    ufFood(['nama' => 'Tahu Goreng']);
    ufFood(['nama' => 'Tempe Bacem']);
    ufFood(['nama' => 'Menunggu Dulu', 'status' => 'menunggu']);
    ufFood(['nama' => 'Sudah Ditolak', 'status' => 'ditolak']);
    ufFood(['nama' => 'Sudah Dihapus'])->delete();
    $other = KategoriMakanan::query()->create(['kategori' => 'Buah']);
    ufFood(['nama' => 'Apel', 'kategori_makanan_id' => $other->id]);

    $names = fn ($query) => collect(
        $this->actingAs($this->user)->get('/data-makanan'.$query)->assertOk()->viewData('page')['props']['foods']['data'],
    )->pluck('nama')->sort()->values()->all();

    expect($names(''))->toBe(['Apel', 'Tahu Goreng', 'Tempe Bacem'])
        ->and($names('?search=tahu'))->toBe(['Tahu Goreng'])
        ->and($names('?kategori='.$other->id))->toBe(['Apel']);
});

it('shows a verified food and hides someone else\'s pending food', function () {
    $verified = ufFood();
    $theirs = ufFood(['status' => 'menunggu', 'pengaju_id' => User::factory()->create()->id]);
    $mine = ufFood(['status' => 'menunggu', 'pengaju_id' => $this->user->id]);

    $this->actingAs($this->user)->get("/data-makanan/{$verified->id}")->assertOk()
        ->assertInertia(fn (Assert $p) => $p->component('User/Makanan/Show')->where('food.nama', 'Tahu Sintetis'));
    $this->actingAs($this->user)->get("/data-makanan/{$mine->id}")->assertOk();
    $this->actingAs($this->user)->get("/data-makanan/{$theirs->id}")->assertNotFound();
});

it('creates a waiting submission owned by the user and ignores fields the user must not set', function () {
    $this->actingAs($this->user)->post('/data-makanan/ajukan', ufBody([
        'status' => 'terverifikasi', 'pengaju_id' => 999, 'nama_pengaju' => 'Admin', 'diverifikasi_oleh' => 1,
        'berat_per_satuan_gram' => '500', 'alasan_penolakan' => 'x',
    ]))->assertRedirect(route('user.foods.mine'));

    $m = Makanan::query()->sole();
    expect($m->status)->toBe('menunggu')->and($m->pengaju_id)->toBe($this->user->id)->and($m->nama_pengaju)->toBe('Rina Santoso')
        ->and($m->diverifikasi_oleh)->toBeNull()->and($m->berat_per_satuan_gram)->toBeNull()->and($m->alasan_penolakan)->toBeNull()
        ->and($m->nama)->toBe('Oseng Tempe Kecap');
    // Waiting rows never reach the AI catalog.
    expect(Food::query()->count())->toBe(0);
});

it('rejects an invalid submission and creates nothing', function (array $override, string $field) {
    $this->actingAs($this->user)->post('/data-makanan/ajukan', ufBody($override))->assertSessionHasErrors($field);

    expect(Makanan::query()->count())->toBe(0);
})->with([
    'no name' => [['nama' => ''], 'nama'],
    'name too long' => [['nama' => str_repeat('a', 101)], 'nama'],
    'unknown category' => [['kategori_makanan_id' => 9999], 'kategori_makanan_id'],
    'portion unit is not offered' => [['satuan' => 'porsi'], 'satuan'],
    'zero serving' => [['kuantitas' => '0'], 'kuantitas'],
    'negative energy' => [['energi' => '-1'], 'energi'],
    'text protein' => [['protein' => 'banyak'], 'protein'],
    'no source' => [['sumber' => ''], 'sumber'],
]);

it('lists my submissions with status and the admin note, and nobody else\'s', function () {
    ufFood(['nama' => 'Punya Saya', 'status' => 'menunggu', 'pengaju_id' => $this->user->id]);
    ufFood(['nama' => 'Ditolak Saya', 'status' => 'ditolak', 'pengaju_id' => $this->user->id, 'alasan_penolakan' => 'Natrium salah satuan']);
    ufFood(['nama' => 'Punya Orang', 'status' => 'menunggu', 'pengaju_id' => User::factory()->create()->id]);

    $rows = $this->actingAs($this->user)->get('/pengajuan-saya')->assertOk()->viewData('page')['props']['submissions'];

    expect(collect($rows)->pluck('nama')->sort()->values()->all())->toBe(['Ditolak Saya', 'Punya Saya'])
        ->and(collect($rows)->firstWhere('nama', 'Ditolak Saya')['alasan_penolakan'])->toBe('Natrium salah satuan');
});

it('end to end: a user submits, the admin verifies, and the AI chat can find the food', function () {
    $this->actingAs($this->user)->post('/data-makanan/ajukan', ufBody());
    $admin = User::factory()->create()->assignRole('admin');
    $m = Makanan::query()->sole();

    $this->actingAs($admin)->post("/admin/verifikasi/{$m->id}/approve")->assertRedirect();

    $food = Food::verified()->where('name', 'Oseng Tempe Kecap')->sole();
    expect($food->energy_kcal)->toBe('248.0000');
    $this->actingAs($this->user)->getJson('/ai/v1/foods?q=oseng')->assertOk()->assertJsonPath('data.0.name', 'Oseng Tempe Kecap');
});
