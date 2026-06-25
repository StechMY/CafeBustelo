<?php

namespace App\Http\Requests\Api;

use App\Services\OtpService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'min:4', 'max:32', Rule::unique('users', 'username')],
            'phone' => ['required', 'string', 'max:40', Rule::unique('users', 'phone')],
            'otp_code' => ['required', 'string', 'digits:6'],
            'referral_code' => ['required', 'string', 'exists:users,referral_code'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => app(OtpService::class)->normalizePhone((string) $this->input('phone')),
        ]);
    }
}
