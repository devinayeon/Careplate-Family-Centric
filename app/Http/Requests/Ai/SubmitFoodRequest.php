<?php

namespace App\Http\Requests\Ai;

use App\Actions\Foods\ReviewCandidate;
use App\Services\Ai\Scan\Decimal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of POST /ai/v1/food-submissions (contracts FoodCandidate). The contract is closed: any other key
 * (status, verified_by, revision, food_id, user_id, decision, ...) at any level is a 422. Nutrients become
 * decimal strings, never floats; the source URL is metadata only and is never fetched.
 * Provider USDA_FDC is reserved for the importer (user rows could otherwise squat triples and be bulk-approved);
 * a MANUAL source cannot pass approval until a manifest entry for it exists.
 */
class SubmitFoodRequest extends FormRequest
{
    private const TOP = ['name', 'aliases', 'basis', 'nutrients', 'source'];

    private const SOURCE = ['provider', 'source_id', 'source_url', 'snapshot_version', 'source_sha256'];

    /** Generous ceiling per 100 g (pure salt is about 39000 mg sodium). */
    private const MAX_NUTRIENT = '100000';

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'aliases' => ['present', 'array', 'max:8'],
            'aliases.*' => ['required', 'string', 'min:1', 'max:80'],
            'basis' => ['required', 'string', 'in:100g'],
            'nutrients' => ['required', 'array'],
            'source' => ['required', 'array'],
            'source.provider' => ['required', 'string', 'in:MANUAL'],
            'source.source_id' => ['required', 'string', 'min:1', 'max:100'],
            'source.source_url' => [
                'required', 'string', 'max:500', 'url:http,https', 'regex:/^https?:\/\//i',
                fn ($attribute, $value, $fail) => self::hasCredentials($value) ? $fail('invalid') : null,
            ],
            'source.snapshot_version' => ['required', 'string', 'min:1', 'max:100'],
            'source.source_sha256' => ['present', 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
        foreach (ReviewCandidate::NUTRIENTS as $field) {
            $rules["nutrients.$field"] = ['present', 'nullable', fn ($attribute, $value, $fail) => self::decimal($value) === null ? $fail('invalid') : null];
        }

        return $rules;
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $input = $this->all();
            $unknown = array_diff_key($input, array_flip(self::TOP))
                + (is_array($input['nutrients'] ?? null) ? array_diff_key($input['nutrients'], array_flip(ReviewCandidate::NUTRIENTS)) : [])
                + (is_array($input['source'] ?? null) ? array_diff_key($input['source'], array_flip(self::SOURCE)) : []);
            if ($unknown !== []) {
                $validator->errors()->add('body', 'unknown field');
            }
        }];
    }

    /**
     * Decimal string with at most 4 decimals in [0, 100000], or null when the value is unusable.
     * Booleans, exponent notation, signs and strings with spaces are refused.
     */
    public static function decimal(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = Decimal::fromNumber($value);
        }
        if (! is_string($value) || preg_match('/^\d{1,6}(\.\d{1,4})?$/', $value) !== 1) {
            return null;
        }

        return Decimal::cmp($value, self::MAX_NUTRIENT) <= 0 ? Decimal::trim($value) : null;
    }

    /**
     * Candidate JSON in the shape the importer writes and ReviewCandidate validates.
     *
     * @return array<string, mixed>
     */
    public function candidate(): array
    {
        $input = $this->validated();
        $nutrients = [];
        foreach (ReviewCandidate::NUTRIENTS as $field) {
            $nutrients[$field] = self::decimal($input['nutrients'][$field]);
        }
        $source = $input['source'];

        return [
            'requires_review' => true,
            'review_reasons' => ['user_submission'],
            'name' => trim($input['name']),
            'aliases' => array_values(array_map('trim', $input['aliases'])),
            'basis' => '100g',
            'nutrients' => $nutrients + ['energy_method' => null],
            'source_description' => null,
            'provenance' => ['source' => [
                'provider' => $source['provider'], 'fdc_id' => $source['source_id'], 'snapshot_version' => $source['snapshot_version'],
                'sha256' => $source['source_sha256'], 'url' => $source['source_url'],
            ]],
        ];
    }

    /** @return array{source_provider: string, source_id: string, source_url: string, snapshot_version: string, source_sha256: string|null} */
    public function source(): array
    {
        $source = $this->validated()['source'];

        return [
            'source_provider' => $source['provider'], 'source_id' => $source['source_id'], 'source_url' => $source['source_url'],
            'snapshot_version' => $source['snapshot_version'], 'source_sha256' => $source['source_sha256'],
        ];
    }

    private static function hasCredentials(mixed $url): bool
    {
        $parts = is_string($url) ? parse_url($url) : false;

        return $parts === false || isset($parts['user']) || isset($parts['pass']);
    }
}
