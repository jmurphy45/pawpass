<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kennel_unit_id'     => ['sometimes', 'nullable', 'string', 'size:26'],
            'status'             => ['sometimes', Rule::in(['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled'])],
            'starts_at'          => ['sometimes', 'date'],
            'ends_at'            => ['sometimes', 'date', 'after:starts_at'],
            'nightly_rate_cents' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'notes'              => ['sometimes', 'nullable', 'string', 'max:2000'],
            'feeding_schedule'   => ['sometimes', 'nullable', 'string', 'max:2000'],
            'medication_notes'   => ['sometimes', 'nullable', 'string', 'max:2000'],
            'behavioral_notes'   => ['sometimes', 'nullable', 'string', 'max:2000'],
            'emergency_contact'  => ['sometimes', 'nullable', 'string', 'max:500'],
            'ignore_vaccination_check' => ['sometimes', 'boolean'],
        ];
    }
}
