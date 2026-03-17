<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in(['one_time', 'subscription', 'unlimited'])],
            'price' => ['required', 'numeric', 'min:0'],
            'credit_count' => [
                Rule::requiredIf(fn () => $this->input('type') === 'one_time'),
                'nullable',
                'integer',
                'min:1',
                Rule::prohibitedIf(fn () => in_array($this->input('type'), ['subscription', 'unlimited'])),
            ],
            'duration_days' => [
                Rule::requiredIf(fn () => $this->input('type') === 'unlimited'),
                'nullable',
                'integer',
                'min:1',
                Rule::prohibitedIf(fn () => in_array($this->input('type'), ['one_time', 'subscription'])),
            ],
            'dog_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
