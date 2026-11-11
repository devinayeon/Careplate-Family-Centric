<?php

use App\Models\Bmi;
use App\Models\KategoriMakanan;
use App\Models\KebutuhanKalori;
use App\Models\Keluarga;
use App\Models\Makanan;
use App\Models\PelacakanMakanan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

// Owner request 2026-11-11: profile account features (Figma 11.1 Profil dan Lencana).
// The page shows who the user is, the streak of logging days and the badges that can be derived from stored data.

beforeEach(function () {
    $this->withoutVite();
    Carbon::setTestNow('2026-11-11 10:00:00');
    $this->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->create(['name' => 'Rina Santoso', 'email' => 'rina@example.test'])->assignRole('user');
    $this->category = KategoriMakanan::query()->create(['kategori' => 'Lauk']);
});

afterEach(fn () => Carbon::setTestNow());

function upFood(): Makanan
{
    return Makanan::query()->firstOrCreate(['nama' => 'Tempe'], [
        'energi' => '118', 'protein' => '9', 'lemak' => '7', 'karbohidrat' => '5', 'kuantitas' => '50', 'satuan' => 'g',
        'sumber' => 'Uji', 'status' => 'terverifikasi', 'kategori_makanan_id' => test()->category->id,
    ]);
}

/** @param list<string> $dates Y-m-d days on which the user logged one food */
function upLogDays(User $user, array $dates): void
{
    $pengguna = $user->pengguna()->firstOrCreate([]);
    foreach ($dates as $date) {
        PelacakanMakanan::query()->create([
            'pengguna_id' => $pengguna->id, 'makanan_id' => upFood()->id, 'tanggal_konsumsi' => $date,
            'waktu_konsumsi' => '08:00:00', 'waktu_makan' => 'sarapan', 'jumlah_porsi' => '1', 'total_kalori' => '118',
        ]);
    }
}

/** @return array<string, mixed> */
function upProps(User $user): array
{
    return test()->actingAs($user)->get('/profile')->assertOk()->viewData('page')['props'];
}

/** @param array<string, mixed> $props */
function upBadge(array $props, string $key): array
{
    return collect($props['lencana']['daftar'])->firstWhere('key', $key);
}

it('sends a guest to login', function () {
    $this->get('/profile')->assertRedirect('/login');
});

it('renders the new profile page for a user with identity, role and age', function () {
    $pengguna = $this->user->pengguna()->create(['tanggal_lahir' => '1988-05-01']);
    $family = Keluarga::query()->create(['nama_keluarga' => 'Keluarga Santoso', 'kode_keluarga' => 'CP1234', 'kepala_keluarga_id' => $pengguna->id]);
    $pengguna->update(['keluarga_id' => $family->id]);

    $this->actingAs($this->user)->get('/profile')->assertOk()->assertInertia(
        fn ($page) => $page->component('User/Profil')->where('profil.name', 'Rina Santoso')->where('profil.email', 'rina@example.test')
            ->where('profil.peran', 'Kepala Keluarga')->where('profil.usia', 38)->where('profil.keluarga', 'Keluarga Santoso'),
    );
});

it('has no role, age or family for a user without profile data, and does not create a profile row on a read', function () {
    $p = upProps($this->user);

    expect($p['profil']['peran'])->toBeNull()->and($p['profil']['usia'])->toBeNull()->and($p['profil']['keluarga'])->toBeNull()
        ->and($p['streak'])->toBe(['saat_ini' => 0, 'terbaik' => 0])
        ->and($this->user->pengguna()->exists())->toBeFalse();
});

it('calls a family member who is not the head "Anggota Keluarga"', function () {
    $head = User::factory()->create()->assignRole('user')->pengguna()->create([]);
    $family = Keluarga::query()->create(['nama_keluarga' => 'Keluarga X', 'kode_keluarga' => 'CP0001', 'kepala_keluarga_id' => $head->id]);
    $head->update(['keluarga_id' => $family->id]);
    $this->user->pengguna()->create(['keluarga_id' => $family->id]);

    expect(upProps($this->user)['profil']['peran'])->toBe('Anggota Keluarga');
});

it('computes the current and best streak from distinct logging days', function (array $days, int $current, int $best) {
    upLogDays($this->user, $days);

    expect(upProps($this->user)['streak'])->toBe(['saat_ini' => $current, 'terbaik' => $best]);
})->with([
    'today and the two days before' => [['2026-11-11', '2026-11-10', '2026-11-09'], 3, 3],
    'not yet today but yesterday keeps the streak alive' => [['2026-11-10', '2026-11-09'], 2, 2],
    'a gap of one full day ends the current streak' => [['2026-11-08', '2026-11-07', '2026-11-06', '2026-11-05'], 0, 4],
    'two foods on one day count once' => [['2026-11-11', '2026-11-11', '2026-11-10'], 2, 2],
    'best run is older than the current one' => [['2026-11-11', '2026-11-01', '2026-11-02', '2026-11-03', '2026-11-04'], 1, 4],
]);

it('unlocks the badges that stored data can prove, with the date, and locks the rest with progress', function () {
    $pengguna = $this->user->pengguna()->create([]);
    upLogDays($this->user, ['2026-11-09', '2026-11-10', '2026-11-11']);
    Bmi::query()->create(['pengguna_id' => $pengguna->id, 'skor' => '22.5', 'keterangan' => 'Normal', 'berat_badan' => '60', 'tinggi_badan' => '163']);

    $p = upProps($this->user);

    expect(upBadge($p, 'langkah_pertama'))->toMatchArray(['terbuka' => true, 'tanggal' => '9 Nov'])
        ->and(upBadge($p, 'kenal_tubuhku')['terbuka'])->toBeTrue()
        ->and(upBadge($p, 'tahu_kebutuhanku')['terbuka'])->toBeFalse()
        ->and(upBadge($p, 'satu_meja')['terbuka'])->toBeFalse()
        ->and(upBadge($p, 'rajin_mencatat_7'))->toMatchArray(['terbuka' => false, 'progres' => ['sekarang' => 3, 'target' => 7, 'satuan' => 'hari']])
        ->and($p['lencana']['terbuka'])->toBe(2)->and($p['lencana']['total'])->toBe(count($p['lencana']['daftar']));
});

it('unlocks the 7 and 30 day badges when the best streak reaches them, dated by the day the target was reached', function () {
    upLogDays($this->user, array_map(fn ($i) => Carbon::parse('2026-10-01')->addDays($i)->toDateString(), range(0, 29)));

    $p = upProps($this->user);

    expect(upBadge($p, 'rajin_mencatat_7'))->toMatchArray(['terbuka' => true, 'tanggal' => '7 Okt'])
        ->and(upBadge($p, 'rajin_mencatat_30'))->toMatchArray(['terbuka' => true, 'tanggal' => '30 Okt']);
});

it('unlocks "Tahu Kebutuhanku", "Satu Meja" and "Penyumbang Data" from their own records', function () {
    $pengguna = $this->user->pengguna()->create([]);
    KebutuhanKalori::query()->create(['pengguna_id' => $pengguna->id, 'skor' => '2000', 'keterangan' => 'Sedang', 'jenis_kelamin' => 'Perempuan', 'berat_badan' => '60', 'tinggi_badan' => '163', 'usia' => 30, 'aktivitas_fisik' => 'Moderate']);
    $family = Keluarga::query()->create(['nama_keluarga' => 'K', 'kode_keluarga' => 'CP0002', 'kepala_keluarga_id' => $pengguna->id]);
    $pengguna->update(['keluarga_id' => $family->id]);
    // A verified submission counts; a waiting one does not.
    upFood()->update(['pengaju_id' => $this->user->id, 'diverifikasi_pada' => '2026-11-05 09:00:00']);

    $p = upProps($this->user);

    expect(upBadge($p, 'tahu_kebutuhanku')['terbuka'])->toBeTrue()->and(upBadge($p, 'satu_meja')['terbuka'])->toBeTrue()
        ->and(upBadge($p, 'penyumbang_data'))->toMatchArray(['terbuka' => true, 'tanggal' => '5 Nov']);

    upFood()->update(['status' => 'menunggu']);
    expect(upBadge(upProps($this->user), 'penyumbang_data')['terbuka'])->toBeFalse();
});

it('never mixes another user\'s records into the badges or the streak', function () {
    upLogDays(User::factory()->create()->assignRole('user'), ['2026-11-11', '2026-11-10']);

    $p = upProps($this->user);

    expect($p['streak'])->toBe(['saat_ini' => 0, 'terbaik' => 0])->and($p['lencana']['terbuka'])->toBe(0);
});

it('sends an admin to the admin profile and keeps the classic page for other roles', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $this->actingAs($admin)->get('/profile')->assertRedirect('/admin/profil');

    $expert = User::factory()->create()->assignRole('ahli_gizi');
    $this->actingAs($expert)->get('/profile')->assertOk()->assertInertia(fn ($page) => $page->component('Profile/Edit'));
});

it('updates name and email, and an email change clears the verification', function () {
    $this->actingAs($this->user)->patch('/profile', ['name' => 'Rina S.', 'email' => 'baru@example.test'])->assertRedirect('/profile');

    $fresh = $this->user->fresh();
    expect($fresh->name)->toBe('Rina S.')->and($fresh->email)->toBe('baru@example.test')->and($fresh->email_verified_at)->toBeNull();
});

it('rejects a duplicate email and an empty name', function () {
    User::factory()->create(['email' => 'dipakai@example.test']);

    $this->actingAs($this->user)->patch('/profile', ['name' => '', 'email' => 'dipakai@example.test'])->assertSessionHasErrors(['name', 'email']);
    expect($this->user->fresh()->email)->toBe('rina@example.test');
});

it('changes the password only with the current one', function () {
    $this->actingAs($this->user)->put('/password', ['current_password' => 'salah', 'password' => 'KataSandiBaru123!', 'password_confirmation' => 'KataSandiBaru123!'])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($this->user)->put('/password', ['current_password' => 'password', 'password' => 'KataSandiBaru123!', 'password_confirmation' => 'KataSandiBaru123!'])
        ->assertSessionHasNoErrors();
    expect(Hash::check('KataSandiBaru123!', $this->user->fresh()->password))->toBeTrue();
});

it('deletes the account only with the right password and removes the user\'s own data', function () {
    upLogDays($this->user, ['2026-11-11']);

    $this->actingAs($this->user)->delete('/profile', ['password' => 'salah'])->assertSessionHasErrors('password');
    expect(User::query()->find($this->user->id))->not->toBeNull();

    $this->actingAs($this->user)->delete('/profile', ['password' => 'password'])->assertRedirect('/');
    expect(User::query()->find($this->user->id))->toBeNull()->and(PelacakanMakanan::query()->count())->toBe(0);
    $this->assertGuest();
});

it('shows the real streak on the dashboard instead of a fixed 0', function () {
    upLogDays($this->user, ['2026-11-11', '2026-11-10']);

    expect($this->actingAs($this->user)->get('/beranda')->viewData('page')['props']['streakHari'])->toBe(2);
});
