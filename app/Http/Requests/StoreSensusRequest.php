<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\File;

class StoreSensusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'uuid'],
            'nama' => ['required', 'string', 'max:100'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'luas_garapan' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
            'lama_menggarap' => ['required', 'integer', 'min:0', 'max:150'],
            'foto' => ['required', File::types(['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'])->max('10mb')],
            'latitude' => ['required', 'numeric', 'between:-80,84'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'gps_accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'captured_at' => ['required', 'date'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($validator->errors()->has('client_uuid')) {
            Log::warning('Sensus ditolak karena client_uuid tidak valid.', [
                'user_id' => $this->user()?->getAuthIdentifier(),
                'reason' => $this->filled('client_uuid') ? 'invalid' : 'missing',
                'content_length' => $this->server('CONTENT_LENGTH'),
                'content_type' => $this->header('Content-Type'),
                'input_count' => count($this->all()),
                'user_agent' => substr($this->userAgent() ?? '', 0, 255),
            ]);
        }

        parent::failedValidation($validator);
    }
}
