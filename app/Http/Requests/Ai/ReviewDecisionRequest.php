<?php

namespace App\Http\Requests\Ai;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of the admin approve and revoke calls. Approve takes reviewer_note (+ optional acknowledge_review_flags),
 * revoke takes reason. The body is closed: any other key (status, revision, verified_by, ...) is a 422.
 */
class ReviewDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function revoking(): bool
    {
        return $this->routeIs('ai.admin.foods.revoke');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $text = ['required', 'string', 'min:1', 'max:1000'];

        return ['expected_revision' => ['required', 'integer', 'min:1', 'max:2147483647']]
            + ($this->revoking()
                ? ['reason' => $text]
                : ['reviewer_note' => $text, 'acknowledge_review_flags' => ['sometimes', 'boolean']]);
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = $this->revoking() ? ['expected_revision', 'reason'] : ['expected_revision', 'reviewer_note', 'acknowledge_review_flags'];
            if (array_diff_key($this->all(), array_flip($allowed)) !== []) {
                $validator->errors()->add('body', 'unknown field');
            }
        }];
    }

    /** @return array{expected_revision: int, text: string, acknowledge: bool} */
    public function decision(): array
    {
        $input = $this->validated();

        return [
            'expected_revision' => (int) $input['expected_revision'],
            'text' => (string) ($input['reason'] ?? $input['reviewer_note']),
            'acknowledge' => (bool) ($input['acknowledge_review_flags'] ?? false),
        ];
    }
}
