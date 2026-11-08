<?php

namespace App\Actions\Foods;

use App\Models\Food;
use App\Models\User;

/** Immutable audit content and the decision hash for ReviewFood. */
class ReviewSnapshot
{
    /**
     * sha256 of the canonical JSON of the decision: action, target id (submission for approve, food
     * for revoke), actor id (null for scripts), audit id, expected revision, trimmed note/reason and
     * the acknowledge-flags choice. Same decision => same hash; anything else differs.
     */
    public static function hash(string $action, string $targetId, ?User $actor, string $auditId, int $expectedRevision, string $note, bool $acknowledgeFlags): string
    {
        return hash('sha256', (string) json_encode([
            'action' => $action,
            'target' => $targetId,
            'actor' => $actor?->getKey(),
            'audit' => $auditId,
            'expected_revision' => $expectedRevision,
            'note' => $note,
            'acknowledge_flags' => $acknowledgeFlags,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array{facts: array<string, mixed>, source: array<string, mixed>, status: string} */
    public static function of(Food $food): array
    {
        $nutrients = [];
        foreach (ReviewCandidate::NUTRIENTS as $field) {
            $nutrients[$field] = $food->{$field};
        }

        return [
            'facts' => [
                'name' => $food->name,
                'aliases' => $food->aliases,
                'basis' => $food->basis,
                'nutrients' => $nutrients,
                'energy_method' => $food->energy_method,
            ],
            'source' => [
                'provider' => $food->source_provider,
                'id' => $food->source_id,
                'url' => $food->source_url,
                'snapshot_version' => $food->snapshot_version,
                'sha256' => $food->source_sha256,
                'description' => $food->source_description,
            ],
            'status' => $food->status,
        ];
    }
}
