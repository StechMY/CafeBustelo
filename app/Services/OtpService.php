<?php

namespace App\Services;

use App\Services\Sms\SmsClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;
use Illuminate\Support\Facades\Crypt;

class OtpService
{
    public function __construct(
        protected SmsClient $smsClient,
    ) {
    }

    public function normalizePhone(string $phone, string $defaultCountryCode = '84'): string
    {
        $phone = trim($phone);

        // Remove spaces, dashes, brackets, plus sign
        $phone = preg_replace('/[\s\-\(\)]+/', '', $phone);
        $phone = ltrim($phone, '+');

        // 0084xxxxxxxx -> 84xxxxxxxx
        if (str_starts_with($phone, '00')) {
            $phone = substr($phone, 2);
        }

        // Vietnam local format: 0xxxxxxxxx -> 84xxxxxxxxx
        if (str_starts_with($phone, '0')) {
            return $defaultCountryCode . substr($phone, 1);
        }

        // Already has country code: 84xxxxxxxxx
        if (str_starts_with($phone, $defaultCountryCode)) {
            return $phone;
        }

        // If user enters without leading 0, example: 912345678 -> 84912345678
        if (preg_match('/^[1-9][0-9]{7,12}$/', $phone)) {
            return $defaultCountryCode . $phone;
        }

        return $phone;
    }

    public function send(string $phone, string $purpose): void
    {
        $phone = $this->normalizePhone($phone);
        request()->attributes->set('otp_phone', $phone);
        request()->attributes->set('otp_result', 'invalid_destination');

        if (! in_array($purpose, ['register', 'bank_account', 'change_password'], true)
            || ! preg_match('/^84[35789][0-9]{8}$/D', $phone)) {
            throw ValidationException::withMessages(['phone' => __('otp.invalid_phone')]);
        }

        if ($purpose !== 'register' && ! request()->user()) {
            throw ValidationException::withMessages(['phone' => __('otp.invalid_destination')]);
        }

        if ($purpose === 'change_password'
            && $phone !== $this->normalizePhone((string) request()->user()->phone)) {
            throw ValidationException::withMessages(['phone' => __('otp.invalid_destination')]);
        }

        if (! config('sms.enabled')) {
            request()->attributes->set('otp_result', 'sending_disabled');
            throw ValidationException::withMessages(['phone' => __('otp.service_unavailable')]);
        }

        request()->attributes->set('otp_result', 'storage_unavailable');
        $key = 'otp:phone:'.hash('sha256', $phone);
        $lock = Cache::lock($key.':lock', 30);
        if (! $lock->get()) {
            request()->attributes->set('otp_result', 'send_in_progress');
            throw ValidationException::withMessages(['phone' => __('otp.please_wait_before_resend')]);
        }

        try {
            $seconds = max(1, (int) config('sms.otp_resend_seconds', 60));
            $recent = DB::table('phone_otps')->where('phone', $phone)
                ->where('created_at', '>', now()->subSeconds($seconds))->exists();

            // Reserve before contacting the provider; failed/uncertain submissions also cool down.
            if ($recent || ! Cache::add($key.':cooldown', true, $seconds)) {
                request()->attributes->set('otp_result', 'cooldown');
                throw ValidationException::withMessages(['phone' => __('otp.please_wait_before_resend')]);
            }

            $this->submit($phone, $purpose);
        } finally {
            $lock->release();
        }
    }

    private function submit(string $phone, string $purpose): void
    {
        $code = (string) random_int(100000, 999999);

        $message = __('otp.sms_message', [
            'code' => $code,
            'minutes' => config('sms.otp_expire_minutes'),
        ]);

        // Snapshot the actual outgoing content, never the plaintext OTP or a later template.
        request()->attributes->set('otp_message_content_redacted', str_replace($code, '******', $message));
        request()->attributes->set('otp_message_locale', app()->getLocale());
        request()->attributes->set('otp_sms_provider', 'abosend');
        request()->attributes->set('otp_sms_attempted_at', now()->toIso8601String());

        try {
            request()->attributes->set('otp_result', 'provider_uncertain');
            $result = $this->smsClient->send($phone, $message);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'phone' => __('otp.sms_send_failed'),
            ]);
        }

        if (!$result['success']) {
            request()->attributes->set('otp_result', 'provider_rejected');
            throw ValidationException::withMessages([
                'phone' => __('otp.sms_send_failed') . ' ' . (''),
            ]);
        }

        request()->attributes->set('otp_sms_uid', $result['uid'] ?? null);
        request()->attributes->set('otp_result', 'accepted_pending_persistence');
        DB::table('phone_otps')->insert([
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'code_encrypted' => Crypt::encryptString($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(config('sms.otp_expire_minutes')),
            'sms_uid' => $result['uid'] ?? null,
            'sms_code' => $result['code'] ?? null,
            'sms_desc' => $result['desc'] ?? null,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        request()->attributes->set('otp_result', 'submitted');
    }

    public function verify(string $phone, string $purpose, string $code): void
    {
        $phone = $this->normalizePhone($phone);

        $otp = DB::table('phone_otps')
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (!$otp) {
            throw ValidationException::withMessages([
                'otp_code' => __('otp.invalid_code'),
            ]);
        }

        if (now()->greaterThan(Carbon::parse($otp->expires_at))) {
            throw ValidationException::withMessages([
                'otp_code' => __('otp.expired_code'),
            ]);
        }

        if ((int) $otp->attempts >= config('sms.otp_max_attempts')) {
            throw ValidationException::withMessages([
                'otp_code' => __('otp.too_many_attempts'),
            ]);
        }

        if (!Hash::check($code, $otp->code_hash)) {
            DB::table('phone_otps')
                ->where('id', $otp->id)
                ->increment('attempts');

            throw ValidationException::withMessages([
                'otp_code' => __('otp.invalid_code'),
            ]);
        }

        DB::table('phone_otps')
            ->where('id', $otp->id)
            ->update([
                'verified_at' => now(),
                'updated_at' => now(),
            ]);
    }
}
