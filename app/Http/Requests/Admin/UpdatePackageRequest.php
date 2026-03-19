<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type', $this->route('package')?->type);

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'type' => ['sometimes', Rule::in(['one_time', 'subscription', 'unlimited'])],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'credit_count' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::prohibitedIf(fn () => in_array($type, ['subscription', 'unlimited'])),
            ],
            'duration_days' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::prohibitedIf(fn () => in_array($type, ['one_time', 'subscription'])),
            ],
            'dog_limit' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'is_recurring_enabled' => ['sometimes', 'boolean'],
            'recurring_interval_days' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
