<?php

namespace App\Http\Requests\Admin\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->where('tenant_id', app('current.tenant.id')),
            ],
        ];
    }
}
