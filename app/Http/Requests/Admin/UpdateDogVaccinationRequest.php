<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDogVaccinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vaccine_name'    => ['sometimes', 'string', 'max:255'],
            'administered_at' => ['sometimes', 'date_format:Y-m-d'],
            'expires_at'      => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'administered_by' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes'           => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
