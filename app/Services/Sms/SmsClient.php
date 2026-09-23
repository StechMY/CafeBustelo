<?php

namespace App\Services\Sms;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SmsClient
{
    public function __construct(
        private readonly SmsBalanceService $balanceService,
    ) {}

    public function send(string $phone, string $message): array
    {
        if (! config('sms.enabled')) {
            return [
                'success' => true,
                'code' => 'DISABLED',
                'desc' => 'SMS disabled',
                'uid' => Str::uuid()->toString(),
                'raw' => [],
            ];
        }

        $orgCode = (string) config('sms.org_code');
        $md5Key = (string) config('sms.md5_key');

        if (blank($orgCode) || blank($md5Key) || blank(config('sms.base_url'))) {
            $this->balanceService->recordSendResult('CONFIGURATION');
            throw new RuntimeException('AboSEND is not configured.');
        }

        $rand = (string) random_int(100000, 999999);
        $payload = [
            'orgCode' => $orgCode,
            'mobiles' => $phone,
            'content' => $message,
            'rand' => $rand,
            'sign' => strtoupper(md5($orgCode.$message.$rand.$md5Key)),
        ];

        if (filled(config('sms.sender_id'))) {
            $payload['oaNumber'] = config('sms.sender_id');
        }

        try {
            // A timeout can occur after acceptance. Do not automatically resubmit an OTP.
            $response = Http::connectTimeout(5)->timeout(15)->asJson()->post(
                rtrim(config('sms.base_url'), '/').'/v2/api/sendSMS',
                $payload,
            );
        } catch (ConnectionException) {
            $this->balanceService->recordSendResult('REQUEST_FAILED');
            // Do not report the transport exception, which can contain request data.
            throw new RuntimeException('AboSEND request failed. Submission status is unknown.');
        }

        if (! $response->successful()) {
            $this->balanceService->recordSendResult('HTTP_ERROR');
            throw new RuntimeException('SMS HTTP failed: '.$response->status());
        }

        $data = $response->json();
        $code = data_get($data, 'code');
        $uid = data_get($data, 'data.sendCode');
        $accepted = in_array($code, [200, '200'], true);
        $success = $accepted && is_string($uid) && trim($uid) !== '' && strlen($uid) <= 64;
        $code = $success ? '200' : ($accepted || ! is_int($code) ? 'INVALID_RESPONSE' : (string) $code);
        $this->balanceService->recordSendResult($code);

        return [
            'success' => $success,
            'code' => $code,
            'desc' => $success ? 'Submitted successfully' : 'SMS submission rejected.',
            'uid' => $success ? $uid : null,
            'raw' => $data,
        ];
    }
}
