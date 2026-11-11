<?php

namespace App\Http\Requests;

use App\Models\PelacakanMakanan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of "Tambah catatan" (POST) and "Ubah catatan" (PUT). Only these keys are read; the owner and the calories are
 * set on the server. A new log needs a verified, not deleted food; an edit may keep the food it already points at
 * even if that food was unverified or deleted later.
 */
class MealLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $log = $this->route('pelacakan');
        $current = $log instanceof PelacakanMakanan ? $log->makanan_id : null;
        $foodRule = Rule::exists('makanan', 'id')->where('status', 'terverifikasi')->whereNull('deleted_at');

        return [
            'makanan_id' => ['required', 'integer', ($current !== null && (int) $this->input('makanan_id') === $current) ? 'min:1' : $foodRule],
            'waktu_makan' => ['required', Rule::in(array_keys(PelacakanMakanan::WAKTU_MAKAN))],
            'jumlah_porsi' => ['required', 'numeric', 'gt:0', 'max:20', 'decimal:0,2'],
            'tanggal_konsumsi' => $this->isMethod('post')
                ? ['required', 'date_format:Y-m-d', 'before_or_equal:today']
                : ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'makanan_id.exists' => 'Pilih makanan dari Data Makanan yang sudah terverifikasi.',
            'makanan_id.required' => 'Pilih makanannya dulu.',
            'waktu_makan.*' => 'Pilih waktu makan.',
            'jumlah_porsi.*' => 'Porsi harus lebih dari 0 dan paling banyak 20.',
            'tanggal_konsumsi.before_or_equal' => 'Catatan tidak bisa untuk hari yang belum terjadi.',
            'tanggal_konsumsi.*' => 'Tanggal tidak valid.',
        ];
    }
}
