<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::in(['bkash', 'nagad'])],
            'amount' => ['required', 'numeric', 'min:1'],
            'transaction_id' => ['required', 'string', 'max:100'],
            'send_from_mobile_number' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
