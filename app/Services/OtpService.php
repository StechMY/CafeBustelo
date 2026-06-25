<?php

namespace App\Services;

use App\Services\Sms\SmsClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

class OtpService
{
    public function __construct(
        protected SmsClient $smsClient,
    ) {}

    public function normalizePhone(string $phone): string
    {
        return preg_replace('/\s+/', '', trim($phone));
    }

    public function send(string $phone, string $purpose): void
    {
        $phone = $this->normalizePhone($phone);

        $latest = DB::table('phone_otps')
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if ($latest && Carbon::parse($latest->created_at)->diffInSeconds(now()) < config('sms.otp_resend_seconds')) {
            throw ValidationException::withMessages([
                'phone' => __('otp.please_wait_before_resend'),
            ]);
        }

        $code = (string) random_int(100000, 999999);

        $message = __('otp.sms_message', [
            'code' => $code,
            'minutes' => config('sms.otp_expire_minutes'),
        ]);

        try {
            $result = $this->smsClient->send($phone, $message);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'phone' => __('otp.sms_send_failed'),
            ]);
        }

        if (! $result['success']) {
            throw ValidationException::withMessages([
                'phone' => __('otp.sms_send_failed').' '.($result['desc'] ?? ''),
            ]);
        }

        DB::table('phone_otps')->insert([
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
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

        if (! $otp) {
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

        if (! Hash::check($code, $otp->code_hash)) {
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
