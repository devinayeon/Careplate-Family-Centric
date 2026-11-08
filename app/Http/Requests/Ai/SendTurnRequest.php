<?php

namespace App\Http\Requests\Ai;

use App\Services\Ai\Scan\Decimal;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of POST /ai/v1/chat/sessions/{session}/turns. Only the four contract fields reach the logic; everything
 * else in the body (user_id, session_id, mode, ...) is dropped. Failures render as INVALID_INPUT.
 */
class SendTurnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $message = $this->input('message');
        if (is_string($message)) {
            // Unicode-aware trim: no-break and ideographic spaces count as blank too.
            $this->merge(['message' => preg_replace('/^[\s\p{Z}\p{Cc}]+|[\s\p{Z}\p{Cc}]+$/u', '', $message) ?? $message]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // `max` counts Unicode characters for strings, not bytes.
            'message' => ['required', 'string', 'min:1', 'max:'.(int) config('ai.chat.max_message_characters', 2000)],
            'client_turn_id' => ['required', 'string', 'uuid'],
            'food_id' => ['nullable', 'string', 'uuid'],
            'amount_g' => ['nullable', 'numeric', 'gt:0', 'lte:'.(int) config('ai.chat.max_amount_g', 2000), 'decimal:0,2'],
        ];
    }

    /**
     * @return array{message: string, client_turn_id: string, food_id: string|null, amount_g: string|null}
     */
    public function turn(): array
    {
        $input = $this->validated();
        $amount = $input['amount_g'] ?? null;

        return [
            'message' => (string) $input['message'],
            'client_turn_id' => strtolower((string) $input['client_turn_id']),
            'food_id' => isset($input['food_id']) ? strtolower((string) $input['food_id']) : null,
            'amount_g' => $amount === null ? null : self::canonicalAmount($amount),
        ];
    }

    /** 50, "50", "50.00" and 50.0 are the same amount: a plain decimal string without trailing zeros. */
    public static function canonicalAmount(int|float|string $amount): string
    {
        $text = is_float($amount) ? Decimal::fromNumber($amount) : trim((string) $amount);

        return Decimal::trim(bcadd(Decimal::num($text), '0', 2));
    }
}
