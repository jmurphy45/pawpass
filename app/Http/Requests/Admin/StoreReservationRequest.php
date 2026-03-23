<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dog_id'             => ['required', 'string', 'size:26'],
            'kennel_unit_id'     => ['nullable', 'string', 'size:26'],
            'starts_at'          => ['required', 'date'],
            'ends_at'            => ['required', 'date', 'after:starts_at'],
            'nightly_rate_cents' => ['nullable', 'integer', 'min:0'],
            'notes'              => ['nullable', 'string', 'max:2000'],
        ];
    }
}
