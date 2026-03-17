<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class GoodwillCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credits' => ['required', 'integer', 'min:1'],
            'note' => ['required', 'string', 'max:500'],
        ];
    }
}
