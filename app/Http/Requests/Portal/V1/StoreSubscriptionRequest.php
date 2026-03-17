<?php

namespace App\Http\Requests\Portal\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
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
                Rule::exists('packages', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true),
            ],
            'dog_id' => [
                'required',
                Rule::exists('dogs', 'id')
                    ->where('customer_id', $customerId)
                    ->where('tenant_id', $tenantId),
            ],
        ];
    }
}
