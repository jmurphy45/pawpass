<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class TransferCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to_dog_id' => ['required', 'string'],
            'credits' => ['required', 'integer', 'min:1'],
        ];
    }
}
