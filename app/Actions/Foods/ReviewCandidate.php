<?php

namespace App\Actions\Foods;

use App\Exceptions\Ai\AiException;
use App\Models\FoodSubmission;
use App\Services\Ai\Foods\SnapshotManifest;

/**
 * Deterministic candidate checks before a submission may publish (no model reviewer). Refusals are
 * 422 INVALID_INPUT with a fixed reason code and never echo candidate content.
 */
class ReviewCandidate
{
    public const NUTRIENTS = ['energy_kcal', 'protein_g', 'fat_g', 'carbohydrate_g', 'sodium_mg', 'sugars_total_g'];

    /** Column fit: foods.energy_method is varchar(255); aliases and the description feed the text search_text. */
    private const MAX_ALIASES = 20;

    private const MAX_DESCRIPTION = 10000;

    public function __construct(private readonly SnapshotManifest $manifest) {}

    /**
     * @return array{name: string, aliases: list<string>, nutrients: array<string, string|null>, energy_method: string|null, source_description: string|null}
     */
    public function validate(FoodSubmission $submission, bool $acknowledgeFlags): array
    {
        /** @var mixed $raw */
        $raw = $submission->candidate;
        /** @var array<string, mixed> $c */
        $c = is_array($raw) ? $raw : [];

        $name = is_string($c['name'] ?? null) ? trim($c['name']) : '';
        $aliases = $c['aliases'] ?? null;
        if ($name === '' || mb_strlen($name) > 255 || ! is_array($aliases) || ! array_is_list($aliases)
            || count($aliases) > self::MAX_ALIASES
            || array_filter($aliases, fn ($a) => ! is_string($a) || mb_strlen($a) > 255) !== [] || ($c['basis'] ?? null) !== '100g'
            || ! is_array($c['nutrients'] ?? null)) {
            throw self::refuse('CANDIDATE_INVALID');
        }

        $nutrients = [];
        foreach (self::NUTRIENTS as $field) {
            $nutrients[$field] = $this->nutrient($c['nutrients'][$field] ?? null);
        }
        $method = $c['nutrients']['energy_method'] ?? null;
        $description = $c['source_description'] ?? null;
        if (($method !== null && (! is_string($method) || mb_strlen($method) > 255))
            || ($description !== null && (! is_string($description) || mb_strlen($description) > self::MAX_DESCRIPTION))) {
            throw self::refuse('CANDIDATE_INVALID');
        }

        $this->assertSource($submission, $c);

        if (($c['requires_review'] ?? false) === true && ! $acknowledgeFlags) {
            throw self::refuse('REVIEW_ACK_REQUIRED');
        }

        return [
            'name' => $name,
            'aliases' => array_map('trim', $aliases),
            'nutrients' => $nutrients,
            'energy_method' => $method,
            'source_description' => $description,
        ];
    }

    /**
     * Unknown stays null; a value must be a non-negative decimal string (or int), never a float. At most 4 decimals
     * (the foods columns are decimal(12,4)): MySQL would round a longer value silently, so it is refused instead.
     */
    private function nutrient(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = is_int($value) ? (string) $value : $value;
        if (! is_string($value) || preg_match('/^\d{1,8}(\.\d{1,4})?$/', $value) !== 1) {
            throw self::refuse('CANDIDATE_INVALID');
        }

        return $value;
    }

    /** @param  array<string, mixed>  $candidate */
    private function assertSource(FoodSubmission $s, array $candidate): void
    {
        foreach ([$s->source_provider, $s->source_id, $s->snapshot_version] as $part) {
            if (trim($part) === '') {
                throw self::refuse('SOURCE_MISSING');
            }
        }
        $manifestSha = $this->manifest->sha256For($s->source_provider, $s->source_id, $s->snapshot_version);
        if ($manifestSha === null) {
            throw self::refuse('SOURCE_NOT_IN_MANIFEST');
        }
        $candidateSha = $candidate['provenance']['source']['sha256'] ?? null;
        if ($s->source_sha256 !== $manifestSha || ($candidateSha !== null && $candidateSha !== $manifestSha)) {
            throw self::refuse('SOURCE_SHA_MISMATCH');
        }
    }

    private static function refuse(string $reason): AiException
    {
        return AiException::for('INVALID_INPUT', "Data yang dikirim tidak valid ({$reason}).");
    }
}
