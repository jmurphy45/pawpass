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
        ];
    }
}
