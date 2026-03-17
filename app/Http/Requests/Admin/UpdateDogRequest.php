<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'breed' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date'],
            'sex' => ['nullable', 'in:male,female,unknown'],
            'vet_name' => ['nullable', 'string', 'max:255'],
            'vet_phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
