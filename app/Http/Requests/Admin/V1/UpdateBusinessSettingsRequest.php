<?php

namespace App\Http\Requests\Admin\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'primary_color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'low_credit_threshold' => ['sometimes', 'integer', 'min:0'],
            'checkin_block_at_zero' => ['sometimes', 'boolean'],
            'payout_schedule' => ['sometimes', 'string', Rule::in(['daily', 'weekly', 'monthly'])],
            'business_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_city' => ['sometimes', 'nullable', 'string', 'max:100'],
            'business_state' => ['sometimes', 'nullable', 'string', 'size:2'],
            'business_zip' => ['sometimes', 'nullable', 'string', 'max:10'],
            'business_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'business_description' => ['sometimes', 'nullable', 'string', 'max:280'],
            'is_publicly_listed' => ['sometimes', 'boolean'],
        ];
    }
}
