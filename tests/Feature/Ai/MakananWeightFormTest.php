<?php

use App\Models\Food;
use App\Models\KategoriMakanan;
use App\Models\Makanan;
use App\Models\User;
use Database\Seeders\AiPermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

// Owner request 2026-11-11 (plan phase 2): the admin form carries the weight per unit and the AI chat follows it.

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolePermissionSeeder::class);
    $this->seed(AiPermissionSeeder::class);
    $this->admin = User::factory()->create()->assignRole('admin');
    $this->category = KategoriMakanan::query()->create(['kategori' => 'Uji']);
});

function weightBody(array $override = []): array
{
    return $override + [
        'nama' => 'Soto Sintetis', 'kategori_makanan_id' => test()->category->id, 'kuantitas' => '1', 'satuan' => 'mangkuk',
        'berat_per_satuan_gram' => '250', 'energi' => '250', 'protein' => '12', 'karbohidrat' => '20', 'lemak' => '10',
        'sumber' => 'Uji', 'status' => 'terverifikasi',
    ];
}

it('stores the weight per unit from the admin form and publishes the food to the AI chat per 100 g', function () {
    $this->actingAs($this->admin)->post('/admin/data-makanan', weightBody())->assertRedirect();

    $m = Makanan::query()->sole();
    expect($m->berat_per_satuan_gram)->toBe('250.00')
        ->and(Food::verified()->where('source_id', (string) $m->id)->sole()->energy_kcal)->toBe('100.0000');
});

it('accepts a missing weight and rejects zero, negative and absurd weights', function (mixed $weight, bool $valid) {
    $response = $this->actingAs($this->admin)->post('/admin/data-makanan', weightBody(['berat_per_satuan_gram' => $weight]));

    $valid ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('berat_per_satuan_gram');
})->with([
    'missing' => [null, true],
    'zero' => ['0', false],
    'negative' => ['-5', false],
    'text' => ['banyak', false],
    'too big' => ['1000000', false],
]);

it('updates the weight and shows it to the edit form', function () {
    $this->actingAs($this->admin)->post('/admin/data-makanan', weightBody());
    $m = Makanan::query()->sole();

    $this->actingAs($this->admin)->post("/admin/data-makanan/{$m->id}", weightBody(['berat_per_satuan_gram' => '500']))->assertRedirect();

    expect($m->fresh()->berat_per_satuan_gram)->toBe('500.00');
    $this->actingAs($this->admin)->get('/admin/data-makanan')->assertInertia(
        fn (Assert $page) => $page->where('foods.data.0.berat_per_satuan_gram', 500),
    );
});
