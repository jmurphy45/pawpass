<?php

namespace App\Http\Requests\Portal;

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
            'dog_id'         => ['required', 'string', 'size:26'],
            'kennel_unit_id' => ['nullable', 'string', 'size:26'],
            'starts_at'      => ['required', 'date'],
            'ends_at'        => ['required', 'date', 'after:starts_at'],
            'notes'          => ['nullable', 'string', 'max:2000'],
            'feeding_schedule'   => ['nullable', 'string', 'max:2000'],
            'medication_notes'   => ['nullable', 'string', 'max:2000'],
            'behavioral_notes'   => ['nullable', 'string', 'max:2000'],
            'emergency_contact'    => ['nullable', 'string', 'max:500'],
            'deposit_amount_cents' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
