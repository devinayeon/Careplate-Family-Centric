<?php

use App\Models\KategoriMakanan;
use App\Models\Makanan;
use App\Models\PelacakanMakanan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

// Owner request 2026-11-11: "Data Makanan pada Pengguna dan Pencatatannya" (Figma 03 Catatan Makanan).
// A user logs verified foods per meal time; totals, the week strip and the dashboard missions read those logs.

beforeEach(function () {
    $this->withoutVite();
    Carbon::setTestNow('2026-11-11 19:00:00');
    $this->seed(RolePermissionSeeder::class);
    $this->category = KategoriMakanan::query()->create(['kategori' => 'Lauk']);
    $this->user = User::factory()->create()->assignRole('user');
});

afterEach(fn () => Carbon::setTestNow());

function mlFood(array $o = []): Makanan
{
    return Makanan::query()->create($o + [
        'nama' => 'Soto ayam', 'energi' => '312', 'protein' => '24', 'lemak' => '18', 'karbohidrat' => '20', 'gula' => '2',
        'natrium' => '600', 'kuantitas' => '1', 'satuan' => 'mangkuk', 'sumber' => 'Uji', 'status' => 'terverifikasi',
        'kategori_makanan_id' => test()->category->id,
    ]);
}

function mlLog(User $user, Makanan $food, array $o = []): PelacakanMakanan
{
    $pengguna = $user->pengguna()->firstOrCreate([]);
    $user->setRelation('pengguna', $pengguna);

    return PelacakanMakanan::query()->create($o + [
        'pengguna_id' => $pengguna->id, 'makanan_id' => $food->id, 'tanggal_konsumsi' => '2026-11-11',
        'waktu_konsumsi' => '12:30:00', 'waktu_makan' => 'makan_siang', 'jumlah_porsi' => '1', 'total_kalori' => $food->energi,
    ]);
}

function mlProps(User $user, string $query = ''): array
{
    return test()->actingAs($user)->get('/catatan-makanan'.$query)->assertOk()->viewData('page')['props'];
}

it('sends a guest to login and refuses a user without the meal-log permission', function () {
    $this->get('/catatan-makanan')->assertRedirect('/login');

    $nobody = User::factory()->create();
    $this->actingAs($nobody)->get('/catatan-makanan')->assertForbidden();
    $this->actingAs($nobody)->post('/catatan-makanan', [])->assertForbidden();
});

it('records a verified food for the signed-in user with calories = energy x portions', function () {
    $food = mlFood();

    $this->actingAs($this->user)->post('/catatan-makanan', [
        'makanan_id' => $food->id, 'waktu_makan' => 'makan_malam', 'jumlah_porsi' => '1.5', 'tanggal_konsumsi' => '2026-11-11',
        'pengguna_id' => 999, 'total_kalori' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $log = PelacakanMakanan::query()->sole();
    expect($log->pengguna_id)->toBe($this->user->fresh()->pengguna->id)
        ->and($log->total_kalori)->toBe('468.00')
        ->and($log->jumlah_porsi)->toBe('1.50')
        ->and($log->waktu_makan)->toBe('makan_malam')
        ->and($log->tanggal_konsumsi->toDateString())->toBe('2026-11-11');
});

it('keeps a zero-calorie food at zero', function () {
    $water = mlFood(['nama' => 'Air mineral', 'energi' => '0']);

    $this->actingAs($this->user)->post('/catatan-makanan', [
        'makanan_id' => $water->id, 'waktu_makan' => 'camilan', 'jumlah_porsi' => '2', 'tanggal_konsumsi' => '2026-11-11',
    ])->assertSessionHasNoErrors();

    expect(PelacakanMakanan::query()->sole()->total_kalori)->toBe('0.00');
});

it('rejects an invalid entry and writes nothing', function (Closure $body, string $field) {
    $this->actingAs($this->user)->post('/catatan-makanan', $body())->assertSessionHasErrors($field);

    expect(PelacakanMakanan::query()->count())->toBe(0);
})->with([
    'unverified food' => [fn () => ['makanan_id' => mlFood(['status' => 'menunggu'])->id, 'waktu_makan' => 'sarapan', 'jumlah_porsi' => '1', 'tanggal_konsumsi' => '2026-11-11'], 'makanan_id'],
    'deleted food' => [fn () => ['makanan_id' => tap(mlFood())->delete()->id, 'waktu_makan' => 'sarapan', 'jumlah_porsi' => '1', 'tanggal_konsumsi' => '2026-11-11'], 'makanan_id'],
    'zero portion' => [fn () => ['makanan_id' => mlFood()->id, 'waktu_makan' => 'sarapan', 'jumlah_porsi' => '0', 'tanggal_konsumsi' => '2026-11-11'], 'jumlah_porsi'],
    'huge portion' => [fn () => ['makanan_id' => mlFood()->id, 'waktu_makan' => 'sarapan', 'jumlah_porsi' => '21', 'tanggal_konsumsi' => '2026-11-11'], 'jumlah_porsi'],
    'unknown meal time' => [fn () => ['makanan_id' => mlFood()->id, 'waktu_makan' => 'brunch', 'jumlah_porsi' => '1', 'tanggal_konsumsi' => '2026-11-11'], 'waktu_makan'],
    'future date' => [fn () => ['makanan_id' => mlFood()->id, 'waktu_makan' => 'sarapan', 'jumlah_porsi' => '1', 'tanggal_konsumsi' => '2026-11-12'], 'tanggal_konsumsi'],
]);

it('shows the day grouped by meal time with totals, a 7-day strip and the calorie target', function () {
    $this->user->pengguna()->create(['kalori' => '1850']);
    $soto = mlFood();
    $tempe = mlFood(['nama' => 'Tempe goreng', 'energi' => '118', 'protein' => '9', 'lemak' => '7', 'karbohidrat' => '5', 'kuantitas' => '50', 'satuan' => 'g']);
    mlLog($this->user, $soto, ['waktu_makan' => 'sarapan', 'waktu_konsumsi' => '07:15:00']);
    mlLog($this->user, $tempe, ['jumlah_porsi' => '2', 'total_kalori' => '236']);
    mlLog($this->user, $soto, ['tanggal_konsumsi' => '2026-11-09']);

    $p = mlProps($this->user);

    expect($p['tanggal'])->toBe('2026-11-11')->and(collect($p['waktuMakan'])->pluck('key')->all())->toBe(['sarapan', 'makan_siang', 'makan_malam', 'camilan']);
    $lunch = collect($p['waktuMakan'])->firstWhere('key', 'makan_siang');
    expect($lunch['total_kalori'])->toEqual(236)->and($lunch['items'][0]['takaran'])->toBe('100 g')->and($lunch['items'][0]['protein'])->toEqual(18)
        ->and($p['ringkasan']['kalori'])->toEqual(548)->and($p['ringkasan']['target_kalori'])->toEqual(1850)
        ->and($p['ringkasan']['protein'])->toEqual(42)->and($p['ringkasan']['natrium'])->toEqual(1800)
        ->and(collect($p['minggu'])->pluck('total_kalori', 'tanggal')->all())->toEqual([
            '2026-11-05' => null, '2026-11-06' => null, '2026-11-07' => null, '2026-11-08' => null,
            '2026-11-09' => 312, '2026-11-10' => null, '2026-11-11' => 548,
        ]);
});

it('never shows another user\'s logs and has no target when none was calculated', function () {
    mlLog(User::factory()->create()->assignRole('user'), mlFood());

    $p = mlProps($this->user);

    expect($p['ringkasan']['kalori'])->toEqual(0)->and($p['ringkasan']['target_kalori'])->toBeNull();
});

it('still shows a log whose food was deleted later, and places legacy logs by their time', function () {
    $food = mlFood(['nama' => 'Sudah Dihapus']);
    mlLog($this->user, $food, ['waktu_makan' => null, 'waktu_konsumsi' => '07:30:00']);
    $food->delete();

    $breakfast = collect(mlProps($this->user)['waktuMakan'])->firstWhere('key', 'sarapan');

    expect($breakfast['items'][0]['nama'])->toBe('Sudah Dihapus');
});

it('refuses a future day and falls back to today for a malformed date', function () {
    expect(mlProps($this->user, '?tanggal=2026-11-20')['tanggal'])->toBe('2026-11-11')
        ->and(mlProps($this->user, '?tanggal=kemarin')['tanggal'])->toBe('2026-11-11')
        ->and(mlProps($this->user, '?tanggal=2026-11-01')['tanggal'])->toBe('2026-11-01');
});

it('updates and deletes only the owner\'s logs', function () {
    $mine = mlLog($this->user, mlFood());
    $theirs = mlLog(User::factory()->create()->assignRole('user'), mlFood());

    $this->actingAs($this->user)->put("/catatan-makanan/{$mine->id}", ['makanan_id' => $mine->makanan_id, 'waktu_makan' => 'camilan', 'jumlah_porsi' => '0.5'])
        ->assertRedirect()->assertSessionHasNoErrors();
    expect($mine->fresh()->total_kalori)->toBe('156.00')->and($mine->fresh()->waktu_makan)->toBe('camilan');

    $this->actingAs($this->user)->put("/catatan-makanan/{$theirs->id}", ['makanan_id' => $theirs->makanan_id, 'waktu_makan' => 'camilan', 'jumlah_porsi' => '2'])->assertNotFound();
    $this->actingAs($this->user)->delete("/catatan-makanan/{$theirs->id}")->assertNotFound();
    expect($theirs->fresh())->not->toBeNull()->and($theirs->fresh()->jumlah_porsi)->toBe('1.00');

    $this->actingAs($this->user)->delete("/catatan-makanan/{$mine->id}")->assertRedirect();
    expect(PelacakanMakanan::query()->find($mine->id))->toBeNull();
});

it('lets the owner edit the portion of a log whose food was unverified later', function () {
    $food = mlFood();
    $log = mlLog($this->user, $food);
    $food->update(['status' => 'ditolak']);

    $this->actingAs($this->user)->put("/catatan-makanan/{$log->id}", ['makanan_id' => $food->id, 'waktu_makan' => 'makan_siang', 'jumlah_porsi' => '2'])
        ->assertSessionHasNoErrors();

    expect($log->fresh()->jumlah_porsi)->toBe('2.00');
});

it('searches only verified foods for the add sheet, at most 10', function () {
    foreach (range(1, 12) as $i) {
        mlFood(['nama' => "Soto {$i}"]);
    }
    mlFood(['nama' => 'Soto Menunggu', 'status' => 'menunggu']);
    mlFood(['nama' => 'Tahu']);

    $data = $this->actingAs($this->user)->getJson('/catatan-makanan/makanan?q=soto')->assertOk()->json('data');

    expect($data)->toHaveCount(10)->and(collect($data)->pluck('nama'))->not->toContain('Soto Menunggu')
        ->and(array_keys($data[0]))->toEqualCanonicalizing(['id', 'nama', 'takaran_saji', 'energi', 'protein', 'lemak', 'karbohidrat', 'foto_makanan_url']);
});

it('opens the add sheet with a verified food preselected, never an unverified one', function () {
    $ok = mlFood();
    $pending = mlFood(['status' => 'menunggu']);

    expect(mlProps($this->user, "?tambah={$ok->id}")['tambah']['id'])->toBe($ok->id)
        ->and(mlProps($this->user, "?tambah={$pending->id}")['tambah'])->toBeNull();
});

it('renders the Catatan Makanan page component', function () {
    $this->actingAs($this->user)->get('/catatan-makanan')->assertInertia(fn (Assert $page) => $page->component('User/CatatanMakanan'));
});

it('completes the dashboard breakfast mission only with a breakfast log, and counts distinct meal times', function () {
    $food = mlFood();
    mlLog($this->user, $food);
    mlLog($this->user, $food);

    $misi = fn () => collect($this->actingAs($this->user)->get('/beranda')->viewData('page')['props']['misi']['list'])->pluck('completed', 'title');
    expect($misi()['Catat sarapan'])->toBeFalse()->and($misi()['Catat tiga waktu makan'])->toBeFalse();

    mlLog($this->user, $food, ['waktu_makan' => 'sarapan']);
    mlLog($this->user, $food, ['waktu_makan' => 'makan_malam']);
    expect($misi()['Catat sarapan'])->toBeTrue()->and($misi()['Catat tiga waktu makan'])->toBeTrue();
});
