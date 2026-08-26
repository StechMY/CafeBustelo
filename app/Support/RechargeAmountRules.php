<?php

namespace App\Support;

class RechargeAmountRules
{
    public static function rules(): array
    {
        return ['required', 'numeric', 'min:450000', 'max:99999999'];
    }
}
