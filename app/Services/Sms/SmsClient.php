<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class SmsClient
{
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

        $response = Http::timeout(15)
            ->retry(2, 300)
            ->get(rtrim(config('sms.base_url'), '/') . '/sms/batch/v2', [
                'appkey' => config('sms.appkey'),
                'appcode' => config('sms.appcode'),
                'appsecret' => config('sms.appsecret'),
                'uid' => Str::uuid()->toString(),
                'phone' => $phone,
                'msg' => $message,
                'extend' => config('sms.extend', ''),
            ]);

        if (! $response->ok()) {
            throw new RuntimeException('SMS HTTP failed: ' . $response->status());
        }

        $data = $response->json();

        return [
            'success' => data_get($data, 'code') === '00000',
            'code' => data_get($data, 'code'),
            'desc' => data_get($data, 'desc'),
            'uid' => data_get($data, 'uid'),
            'raw' => $data,
        ];
    }
}