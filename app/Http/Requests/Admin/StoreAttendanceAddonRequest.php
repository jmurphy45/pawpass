<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'addon_type_id' => ['required', 'string', 'max:26'],
            'quantity'      => ['sometimes', 'integer', 'min:1'],
            'note'          => ['nullable', 'string'],
        ];
    }
}
