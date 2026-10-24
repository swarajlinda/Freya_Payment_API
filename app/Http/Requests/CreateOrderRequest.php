<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'      => ['required', 'numeric', 'min:1', 'max:1000000'], // major units, e.g. 499.50
            'currency'    => ['sometimes', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes'       => ['nullable', 'array', 'max:15'],
            'notes.*'     => ['nullable', 'string', 'max:255'],
        ];
    }
}
