<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationAddonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'addon_type_id' => ['required', 'string', 'size:26'],
            'quantity'      => ['sometimes', 'integer', 'min:1', 'max:99'],
            'note'          => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
