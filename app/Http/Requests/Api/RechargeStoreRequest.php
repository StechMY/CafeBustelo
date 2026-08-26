<?php

namespace App\Http\Requests\Api;

use App\Models\RechargeMethod;
use App\Support\RechargeAmountRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RechargeStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => RechargeAmountRules::rules(),
            'recharge_method_id' => [
                'required',
                'integer',
                Rule::exists('recharge_methods', 'id')->where('status', RechargeMethod::STATUS_ACTIVE),
            ],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'receipt' => ['required', 'file', 'image', 'max:5120'],
        ];
    }
}
