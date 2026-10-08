<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSensusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:100'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'luas_garapan' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'lama_menggarap' => ['required', 'integer', 'min:0', 'max:150'],
        ];
    }
}
