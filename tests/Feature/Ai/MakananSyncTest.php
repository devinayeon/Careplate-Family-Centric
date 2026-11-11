<?php

use App\Actions\Foods\ReviewFood;
use App\Exceptions\Ai\AiException;
use App\Models\Food;
use App\Models\FoodRevision;
use App\Models\FoodSubmission;
use App\Models\KategoriMakanan;
use App\Models\Makanan;
use App\Models\User;
use App\Services\Ai\Foods\MakananSource;
use App\Services\Ai\Foods\SyncMakananToFoods;
use Database\Seeders\AiPermissionSeeder;
use Illuminate\Database\Eloquent\Collection;

beforeEach(fn () => test()->seed(AiPermissionSeeder::class));

/** @param array<string, mixed> $override */
function makanan(array $override = []): Makanan
{
    $category = KategoriMakanan::query()->firstOrCreate(['kategori' => 'Uji']);

    return Makanan::query()->create($override + [
        'nama' => 'Tahu Sintetis', 'energi' => '80.00', 'lemak' => '4.80', 'protein' => '8.00', 'kuantitas' => '100.00',
        'satuan' => 'gram', 'karbohidrat' => '1.90', 'gula' => '0.00', 'natrium' => '0.00', 'sumber' => 'Uji',
        'status' => 'terverifikasi', 'kategori_makanan_id' => $category->id,
    ]);
}

function aiFoods(Makanan $m): Collection
{
    return Food::query()->where('source_provider', MakananSource::PROVIDER)->where('source_id', (string) $m->id)->get();
}

it('publishes a verified gram makanan as a verified AI food converted to per 100 g', function () {
    $m = makanan(['nama' => 'Tempe Sintetis', 'energi' => '200.00', 'lemak' => '10.00', 'protein' => '20.00',
        'kuantitas' => '50.00', 'karbohidrat' => '8.00', 'natrium' => '150.00', 'gula' => '2.50']);

    $food = aiFoods($m)->sole();

    expect($food->status)->toBe('verified')
        ->and($food->name)->toBe('Tempe Sintetis')
        ->and($food->energy_kcal)->toBe('400.0000')
        ->and($food->protein_g)->toBe('40.0000')
        ->and($food->fat_g)->toBe('20.0000')
        ->and($food->carbohydrate_g)->toBe('16.0000')
        ->and($food->sodium_mg)->toBe('300.0000')
        ->and($food->sugars_total_g)->toBe('5.0000')
        ->and($food->verifier_audit_id)->toBe('sync:makanan')
        ->and($food->source_description)->toContain('Master makanan Careplate');
});

it('keeps unknown sugars and sodium null instead of publishing the default 0', function () {
    $food = aiFoods(makanan())->sole();

    expect($food->sodium_mg)->toBeNull()->and($food->sugars_total_g)->toBeNull();
});

it('does not publish units it cannot convert to 100 g (ml, portion, piece) or unverified rows', function (array $row, string $reason) {
    $m = makanan($row);

    expect(aiFoods($m))->toHaveCount(0)
        ->and(app(SyncMakananToFoods::class)->sync($m))->toBe("skipped:{$reason}");
})->with([
    'ml' => [['satuan' => 'ml'], 'unit_not_gram'],
    'portion' => [['satuan' => 'porsi'], 'unit_not_gram'],
    'piece' => [['satuan' => 'buah'], 'unit_not_gram'],
    'waiting' => [['status' => 'menunggu'], 'not_verified'],
    'rejected' => [['status' => 'ditolak'], 'not_verified'],
    'zero quantity' => [['kuantitas' => '0.00'], 'quantity_invalid'],
]);

it('follows the admin: verified publishes, rejected revokes, verified again publishes a new version', function () {
    $m = makanan(['status' => 'menunggu']);
    expect(aiFoods($m))->toHaveCount(0);

    $m->update(['status' => 'terverifikasi']);
    expect(Food::verified()->where('source_id', (string) $m->id)->count())->toBe(1);

    $m->update(['status' => 'ditolak']);
    expect(Food::verified()->where('source_id', (string) $m->id)->count())->toBe(0)
        ->and(aiFoods($m)->sole()->status)->toBe('revoked');

    $m->update(['status' => 'terverifikasi']);
    $all = aiFoods($m);
    expect($all)->toHaveCount(2)
        ->and($all->where('status', 'verified'))->toHaveCount(1)
        ->and($all->pluck('snapshot_version')->unique())->toHaveCount(2);
});

it('a changed value revokes the old version and publishes exactly one new verified version', function () {
    $m = makanan();
    $m->update(['protein' => '9.00']);

    $verified = Food::verified()->where('source_id', (string) $m->id)->get();
    expect($verified)->toHaveCount(1)
        ->and($verified->first()->protein_g)->toBe('9.0000')
        ->and(aiFoods($m))->toHaveCount(2);
});

it('saving without a content change does not create revisions', function () {
    $m = makanan();
    $revisions = FoodRevision::query()->count();

    $m->update(['kuantitas' => '100.00']);
    $m->touch();

    expect(FoodRevision::query()->count())->toBe($revisions)
        ->and(app(SyncMakananToFoods::class)->sync($m->fresh()))->toBe('unchanged');
});

it('a soft delete revokes and a restore publishes again', function () {
    $m = makanan();
    $m->delete();
    expect(Food::verified()->where('source_id', (string) $m->id)->count())->toBe(0);

    $m->restore();
    expect(Food::verified()->where('source_id', (string) $m->id)->count())->toBe(1);
});

it('chat search finds the published food and not an unverified one', function () {
    makanan(['nama' => 'Oncom Sintetis']);
    makanan(['nama' => 'Cilok Sintetis', 'status' => 'menunggu']);
    $user = User::factory()->create()->assignRole('nutritionist');

    $names = collect(test()->actingAs($user)->getJson('/ai/v1/foods?limit=5')->assertOk()->json('data'))->pluck('name');

    expect($names)->toContain('Oncom Sintetis')->not->toContain('Cilok Sintetis');
});

it('refuses to approve a forged makanan submission (wrong hash, unverified row, version from other content)', function () {
    $m = makanan(['status' => 'menunggu']);
    $forged = FoodSubmission::query()->create([
        'user_id' => null, 'status' => 'pending', 'revision_counter' => 1, 'source_provider' => MakananSource::PROVIDER,
        'source_id' => (string) $m->id, 'snapshot_version' => 'deadbeef0000-1', 'source_sha256' => str_repeat('a', 64),
        'candidate' => ['name' => 'Palsu', 'aliases' => ['Palsu'], 'basis' => '100g', 'requires_review' => false,
            'nutrients' => ['energy_kcal' => '1', 'protein_g' => '1', 'fat_g' => '1', 'carbohydrate_g' => '1', 'sodium_mg' => null, 'sugars_total_g' => null]],
    ]);

    expect(fn () => app(ReviewFood::class)->approve($forged, null, 'test', 1, 'x'))->toThrow(AiException::class);
    expect(Food::query()->where('name', 'Palsu')->count())->toBe(0);
});

it('a failing sync never breaks saving the makanan', function () {
    $this->mock(ReviewFood::class)->shouldReceive('approve')->andThrow(new RuntimeException('boom'));

    $m = makanan(['nama' => 'Tetap Tersimpan']);

    expect(Makanan::query()->whereKey($m->id)->exists())->toBeTrue()->and(aiFoods($m))->toHaveCount(0);
});

it('can be switched off, and ai:sync-makanan backfills and reports', function () {
    config(['ai.sync_makanan' => false]);
    $a = makanan(['nama' => 'Satu']);
    $b = makanan(['nama' => 'Dua', 'satuan' => 'ml']);
    expect(aiFoods($a))->toHaveCount(0);

    $this->artisan('ai:sync-makanan')->expectsOutputToContain('published: 1')->expectsOutputToContain('skipped:unit_not_gram: 1')
        ->expectsOutputToContain('failed: 0')->assertSuccessful();
    expect(aiFoods($a))->toHaveCount(1)->and(aiFoods($b))->toHaveCount(0);

    $this->artisan('ai:sync-makanan')->expectsOutputToContain('unchanged: 1')->assertSuccessful();
});

// Owner request 2026-11-11 (plan phase 2): a weight per unit lets portions, pieces and ml reach the chat with real numbers.
it('converts a non-gram unit to per 100 g with the weight per unit the admin gave', function (array $row, array $expected) {
    $food = aiFoods(makanan($row + ['nama' => 'Nasi Sintetis']))->sole();

    expect($food->status)->toBe('verified')
        ->and($food->energy_kcal)->toBe($expected['energy'])
        ->and($food->protein_g)->toBe($expected['protein']);
})->with([
    'one portion of 150 g' => [['satuan' => 'porsi', 'kuantitas' => '1.00', 'berat_per_satuan_gram' => '150.00', 'energi' => '300.00', 'protein' => '6.00'], ['energy' => '200.0000', 'protein' => '4.0000']],
    'two pieces of 60 g' => [['satuan' => 'butir', 'kuantitas' => '2.00', 'berat_per_satuan_gram' => '60.00', 'energi' => '180.00', 'protein' => '15.00'], ['energy' => '150.0000', 'protein' => '12.5000']],
    'ml with density 1.2 g per ml' => [['satuan' => 'ml', 'kuantitas' => '250.00', 'berat_per_satuan_gram' => '1.20', 'energi' => '300.00', 'protein' => '3.00'], ['energy' => '100.0000', 'protein' => '1.0000']],
]);

it('ignores the weight for grams and still skips a non-gram unit without a usable weight', function (array $row, string $reason) {
    $m = makanan($row);

    expect(aiFoods($m))->toHaveCount(0)
        ->and(app(SyncMakananToFoods::class)->sync($m))->toBe("skipped:{$reason}");
})->with([
    'porsi, no weight' => [['satuan' => 'porsi', 'berat_per_satuan_gram' => null], 'unit_not_gram'],
    'porsi, zero weight' => [['satuan' => 'porsi', 'berat_per_satuan_gram' => '0.00'], 'unit_not_gram'],
]);

it('gram rows use the quantity itself even when a weight is stored', function () {
    $food = aiFoods(makanan(['kuantitas' => '50.00', 'energi' => '200.00', 'berat_per_satuan_gram' => '999.00']))->sole();

    expect($food->energy_kcal)->toBe('400.0000');
});

it('a changed weight republishes exactly one new verified version', function () {
    $m = makanan(['satuan' => 'porsi', 'kuantitas' => '1.00', 'berat_per_satuan_gram' => '100.00', 'energi' => '200.00']);
    $m->update(['berat_per_satuan_gram' => '200.00']);

    $verified = Food::verified()->where('source_id', (string) $m->id)->get();
    expect($verified)->toHaveCount(1)->and($verified->first()->energy_kcal)->toBe('100.0000')->and(aiFoods($m))->toHaveCount(2);
});

it('clearing the weight of a non-gram row revokes its AI food', function () {
    $m = makanan(['satuan' => 'porsi', 'kuantitas' => '1.00', 'berat_per_satuan_gram' => '150.00']);
    expect(Food::verified()->where('source_id', (string) $m->id)->count())->toBe(1);

    $m->update(['berat_per_satuan_gram' => null]);

    expect(Food::verified()->where('source_id', (string) $m->id)->count())->toBe(0);
});
