<?php

namespace App\Services\Ai\Foods;

use App\Models\FoodSubmission;

/**
 * Turns matched Foundation Foods records into pending food_submissions. It never creates foods
 * and never approves. Idempotent on (source_provider, source_id, snapshot_version): an existing
 * row is reported "unchanged" or "changed" and is never rewritten.
 */
class UsdaFoundationImporter
{
    private const DROP_FROM_ALIAS = [' tanpa tambahan gula', ' tanpa tambahan', ' bagian dapat dimakan', ' basis gram', ' varian spesifik', ' varian sumber'];

    public function __construct(
        private readonly FoundationMatcher $matcher,
        private readonly FoundationNutrients $nutrients,
    ) {}

    /**
     * @param  list<array<string, string>>  $curation  food-curation.csv rows
     * @param  list<array<string, mixed>>  $foods  FoundationFoods
     * @param  array{provider: string, version: string, sha256: string, url: string}  $snapshot
     * @return array{rows: list<array<string, mixed>>, counts: array<string, int>, source_ids: list<string>}
     */
    public function import(array $curation, array $foods, array $snapshot, int $limit = 20): array
    {
        usort($curation, fn (array $a, array $b) => (int) $a['priority'] <=> (int) $b['priority']);
        $counts = ['matched' => 0, 'skipped' => 0, 'created' => 0, 'unchanged' => 0, 'changed' => 0];
        $rows = [];
        $claimed = [];

        foreach (array_slice($curation, 0, $limit) as $row) {
            $match = $this->matcher->match($row, $foods);
            $id = $match['status'] === 'matched' ? (string) $match['food']['fdcId'] : null;
            if ($id !== null && isset($claimed[$id])) {
                $match = ['status' => 'skipped', 'reason' => "record {$id} was already matched by {$claimed[$id]}"];
            }
            if ($match['status'] === 'skipped') {
                $counts['skipped']++;
                $rows[] = ['candidate_id' => $row['candidate_id'], 'status' => 'skipped', 'reason' => $match['reason']];

                continue;
            }
            $claimed[$id] = $row['candidate_id'];
            $counts['matched']++;
            $outcome = $this->persist($row, $match, $snapshot);
            $counts[$outcome['status']]++;
            $rows[] = ['candidate_id' => $row['candidate_id'], 'fdc_id' => $id, 'description' => (string) $match['food']['description']] + $outcome;
        }
        $ids = array_keys($claimed);
        sort($ids, SORT_NATURAL);

        return ['rows' => $rows, 'counts' => $counts, 'source_ids' => array_map('strval', $ids)];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $snapshot
     * @return array{status: string, changed?: list<string>}
     */
    private function persist(array $row, array $match, array $snapshot): array
    {
        $id = (string) $match['food']['fdcId'];
        $candidate = $this->candidate($row, $match, $snapshot);
        $key = ['source_provider' => $snapshot['provider'], 'source_id' => $id, 'snapshot_version' => $snapshot['version']];
        $existing = FoodSubmission::where($key)->first();

        if ($existing === null) {
            FoodSubmission::create($key + [
                'user_id' => null, 'status' => 'pending', 'revision_counter' => 1, 'candidate' => $candidate,
                'source_url' => "https://fdc.nal.usda.gov/food-details/{$id}/nutrients", 'source_sha256' => $snapshot['sha256'],
            ]);

            return ['status' => 'created'];
        }
        $changed = $this->differences((array) $existing->candidate, $candidate);
        if ($existing->source_sha256 !== $snapshot['sha256']) {
            $changed[] = 'source_sha256';
        }

        return $changed === [] ? ['status' => 'unchanged'] : ['status' => 'changed', 'changed' => $changed];
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, mixed>  $match
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function candidate(array $row, array $match, array $snapshot): array
    {
        $food = $match['food'];
        $read = $this->nutrients->extract($food);

        $candidate = [
            'name' => $row['display_name_id'],
            'aliases' => $this->aliases($row['display_name_id']),
            'basis' => '100g',
            'nutrients' => $read['nutrients'],
            'source_description' => (string) $food['description'],
            'provenance' => [
                'source' => [
                    'provider' => $snapshot['provider'], 'fdc_id' => (string) $food['fdcId'],
                    'data_type' => (string) ($food['dataType'] ?? ''), 'description' => (string) $food['description'],
                    'publication_date' => $food['publicationDate'] ?? null,
                    'snapshot_version' => $snapshot['version'], 'sha256' => $snapshot['sha256'], 'url' => $snapshot['url'],
                ],
                'match' => [
                    'candidate_id' => $row['candidate_id'], 'query' => $row['search_query_en'],
                    'required_state' => $row['required_state'], 'kind' => $match['kind'], 'alternatives' => $match['alternatives'],
                ],
            ] + $read['provenance'],
        ];

        // Round-trip so the compared shape equals what a JSON column returns.
        return json_decode((string) json_encode($candidate), true);
    }

    /** @return list<string> */
    private function aliases(string $name): array
    {
        $lower = mb_strtolower(trim($name));

        return array_values(array_unique([$lower, trim(str_replace(self::DROP_FROM_ALIAS, '', $lower))]));
    }

    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $fresh
     * @return list<string>
     */
    private function differences(array $stored, array $fresh): array
    {
        $out = [];
        foreach (array_unique(array_merge(array_keys($stored), array_keys($fresh))) as $key) {
            if ($key === 'nutrients') {
                foreach (array_unique(array_merge(array_keys($stored[$key] ?? []), array_keys($fresh[$key] ?? []))) as $field) {
                    if (($stored[$key][$field] ?? null) !== ($fresh[$key][$field] ?? null)) {
                        $out[] = "nutrients.{$field}";
                    }
                }
            } elseif (json_encode($stored[$key] ?? null) !== json_encode($fresh[$key] ?? null)) {
                $out[] = (string) $key;
            }
        }

        return $out;
    }
}
