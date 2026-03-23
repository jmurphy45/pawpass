<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAddonTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'is_active'   => ['sometimes', 'boolean'],
            'sort_order'  => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
