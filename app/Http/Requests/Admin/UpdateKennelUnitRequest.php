<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKennelUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['sometimes', 'string', 'max:255'],
            'type'        => ['sometimes', Rule::in(['standard', 'suite', 'large', 'run'])],
            'capacity'    => ['sometimes', 'integer', 'min:1', 'max:100'],
            'description'        => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_active'          => ['sometimes', 'boolean'],
            'sort_order'         => ['sometimes', 'integer', 'min:0'],
            'nightly_rate_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }
}
