<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wallet_type' => ['required', 'string', 'in:recharge,withdrawable'],
            'coupon_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
