<?php

namespace App\Services\Ai\Foods;

use App\Actions\Foods\ReviewFood;
use App\Models\Food;
use App\Models\FoodSubmission;
use App\Models\Makanan;

/**
 * Publishes the team's verified `makanan` rows as AI foods, only through ReviewFood (the single publish path).
 *
 * One AI food per CONTENT version: a changed row revokes the old food and approves a new one (source triple
 * provider + makanan id + "<hash12>-<n>"), so the revision history of every version stays immutable and the
 * search never sees two verified versions of one makanan. A row that is no longer eligible is revoked.
 */
final class SyncMakananToFoods
{
    public const AUDIT_ID = 'sync:makanan';

    public function __construct(private readonly ReviewFood $review) {}

    /** @return 'published'|'unchanged'|'revoked'|string  `skipped:<reason>` when the row was never publishable */
    public function sync(Makanan $makanan): string
    {
        $sourceId = (string) $makanan->getKey();
        $verified = Food::query()->verified()->where('source_provider', MakananSource::PROVIDER)->where('source_id', $sourceId)->get();
        $reason = MakananSource::ineligibleReason($makanan);

        if ($reason !== null) {
            foreach ($verified as $food) {
                $this->revoke($food, "Makanan tidak lagi dapat dipakai AI ({$reason}).");
            }

            return $verified->isEmpty() ? "skipped:{$reason}" : 'revoked';
        }

        $hash = MakananSource::contentHash($makanan);
        $prefix = substr($hash, 0, MakananSource::VERSION_PREFIX_LENGTH);
        if ($verified->contains(fn (Food $f) => str_starts_with($f->snapshot_version, $prefix.'-'))) {
            return 'unchanged';
        }
        foreach ($verified as $food) {
            $this->revoke($food, 'Nilai makanan diubah; versi baru diterbitkan.');
        }

        $version = $prefix.'-'.(FoodSubmission::query()->where('source_provider', MakananSource::PROVIDER)->where('source_id', $sourceId)->count() + 1);
        $submission = FoodSubmission::query()->create([
            'user_id' => null,
            'status' => 'pending',
            'revision_counter' => 1,
            'candidate' => $this->candidate($makanan, $hash, $version),
            'source_provider' => MakananSource::PROVIDER,
            'source_id' => $sourceId,
            'source_url' => null,
            'snapshot_version' => $version,
            'source_sha256' => $hash,
        ]);
        $this->review->approve(
            $submission,
            null,
            self::AUDIT_ID,
            1,
            'Makanan diverifikasi admin pada master makanan Careplate; nilai per 100 g dihitung dari kuantitas dan satuan gram.',
        );

        return 'published';
    }

    private function revoke(Food $food, string $reason): void
    {
        $this->review->revoke($food, null, self::AUDIT_ID, $food->current_revision, $reason);
    }

    /** @return array<string, mixed> */
    private function candidate(Makanan $m, string $hash, string $version): array
    {
        $name = trim((string) $m->nama);
        $candidate = [
            'requires_review' => false,
            'review_reasons' => [],
            'name' => $name,
            'aliases' => [$name],
            'basis' => '100g',
            'nutrients' => MakananSource::per100g($m) + ['energy_method' => 'Master makanan Careplate (per kuantitas, dikonversi ke 100 g)'],
            'source_description' => 'Master makanan Careplate: '.$name.($m->sumber ? ' ('.trim((string) $m->sumber).')' : ''),
            'provenance' => ['source' => [
                'provider' => MakananSource::PROVIDER, 'makanan_id' => (string) $m->getKey(),
                'snapshot_version' => $version, 'sha256' => $hash,
                'portion' => ['quantity' => (string) $m->kuantitas, 'unit' => (string) $m->satuan],
            ]],
        ];

        return json_decode((string) json_encode($candidate), true);
    }
}
