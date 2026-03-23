<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreDogVaccinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vaccine_name'    => ['required', 'string', 'max:255'],
            'administered_at' => ['required', 'date_format:Y-m-d'],
            'expires_at'      => ['nullable', 'date_format:Y-m-d', 'after:administered_at'],
            'administered_by' => ['nullable', 'string', 'max:255'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ];
    }
}
