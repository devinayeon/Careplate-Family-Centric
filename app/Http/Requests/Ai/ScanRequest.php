<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Exactly one multipart `image` part. Type, size and pixel limits are judged from the
 * bytes by ImagePreparer (415/413), so an upload PHP already rejected as too large
 * must still reach it: no `required`/`file`/`image` rules here.
 */
class ScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->file('image') instanceof UploadedFile) {
                    $validator->errors()->add('image', 'invalid');
                }
            },
        ];
    }

    public function upload(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('image');
    }
}
