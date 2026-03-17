<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CheckinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dogs' => ['required', 'array', 'min:1'],
            'dogs.*.dog_id' => ['required', 'string'],
            'dogs.*.zero_credit_override' => ['nullable', 'boolean'],
            'dogs.*.override_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
