<?php

namespace App\Http\Requests\Portal\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = $this->user()->customer_id;
        $tenantId = app('current.tenant.id');

        return [
            'package_id' => [
                'required',
                Rule::exists('packages', 'id')->where('tenant_id', $tenantId),
            ],
            'dog_ids' => ['required', 'array', 'min:1'],
            'dog_ids.*' => [
                'required',
                Rule::exists('dogs', 'id')
                    ->where('customer_id', $customerId)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'active'),
            ],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country'     => ['nullable', 'string', 'size:2'],
        ];
    }
}
