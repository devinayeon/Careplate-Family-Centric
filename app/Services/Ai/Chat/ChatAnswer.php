<?php

namespace App\Services\Ai\Chat;

/**
 * What ChatComposer decided for one turn: the renderer-owned part of the ChatResponse
 * (mode, retrieval_status, answer, claims, citations, general_knowledge_notice, warnings, in that order),
 * the foods the turn identified (stored as food_refs for follow-ups and revalidated at finalize),
 * and the error code of a model step that was skipped in favour of the server template (null = none).
 */
final class ChatAnswer
{
    /**
     * @param  array<string, mixed>  $part
     * @param  list<array{food_id: string, revision: int}>  $foodRefs
     */
    public function __construct(
        public readonly array $part,
        public readonly array $foodRefs = [],
        public readonly ?string $fallback = null,
    ) {}
}
