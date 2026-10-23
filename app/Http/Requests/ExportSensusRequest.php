<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportSensusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isAdminExport = $this->routeIs('admin.sensus.export');

        return [
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['required', 'string', 'distinct', Rule::in([
                'id', 'nama', 'nama_kth', 'nama_penunjuk', 'kabupaten_kota', 'no_hp', 'luas_garapan', 'lama_menggarap',
                'latitude', 'longitude', 'utm_x', 'utm_y', 'utm_epsg',
                'gps_accuracy_m', 'captured_at', 'updated_at', 'creator_name', 'foto_url',
            ])],
            'scope' => $isAdminExport
                ? ['required', 'string', Rule::in(['all', 'user'])]
                : ['sometimes', 'string', Rule::in(['mine'])],
            'user_id' => $isAdminExport
                ? ['required_if:scope,user', 'prohibited_unless:scope,user', 'integer', 'exists:users,id']
                : ['prohibited'],
        ];
    }
}
