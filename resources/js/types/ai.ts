// Shapes of the /ai/v1 JSON API (see ai/README.md). Unknown nutrient values are null, never 0.

export interface NutrientReading {
    value: number | null;
    qualifier: string;
    unit: string;
    evidence: string | null;
    label_percent_akg: number | null;
}

export interface ScanResult {
    schema_version: string;
    request_id: string;
    extraction_status: 'complete' | 'partial';
    extraction: {
        label_basis: string;
        serving_size: {
            value: number | null;
            unit: string | null;
            household_text: string | null;
        } | null;
        servings_per_package: number | null;
        nutrients: Record<string, NutrientReading | undefined>;
        needs_review: boolean;
    };
    normalized_per_serving: Record<string, number | null>;
    assessment: {
        daily_reference_contribution_percent: Record<string, number | null>;
        flags: string[];
        verdict: string;
        summary: string | null;
    };
    warnings: string[];
}

export interface ChatClaim {
    food_id: string;
    revision: number;
    nutrient: string;
    canonical_value: number | null;
    unit: string;
    requested_amount: number | null;
    derived_value: number | null;
    citation_id: string;
}

export interface ChatCitation {
    id: string;
    food_id: string;
    revision: number;
    food_name: string;
    source_reference: string | null;
    verified_at: string | null;
}

export interface ChatAnswer {
    request_id: string;
    session_id: string;
    turn_id: string;
    client_turn_id: string;
    sequence: number;
    mode: 'database' | 'general' | 'clarification' | 'referral';
    retrieval_status: 'found' | 'no_match' | 'ambiguous' | 'not_needed';
    answer: string;
    claims: ChatClaim[];
    citations: ChatCitation[];
    general_knowledge_notice: string | null;
    warnings: string[];
}

export interface ChatSessionItem {
    id: string;
    state: 'active';
    title: string | null;
    created_at: string;
    updated_at: string;
}

export interface ChatTurnItem {
    id: string;
    client_turn_id: string;
    sequence: number;
    state: 'pending' | 'completed' | 'failed';
    user_content: string;
    response: ChatAnswer | null;
    failure_code: string | null;
    created_at: string;
}

/** One chat bubble pair as the screen keeps it. */
export interface ChatMessage {
    key: string;
    clientTurnId: string;
    question: string;
    state: 'sending' | 'done' | 'failed';
    answer: ChatAnswer | null;
    error: string | null;
    retryable: boolean;
    at: string;
}
