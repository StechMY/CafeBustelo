<?php

namespace App\Services\PaymentGateway;

use App\Exceptions\Qc168GatewayException;
use App\Exceptions\Qc168UncertainSubmissionException;
use App\Models\RechargeRequest;
use App\Models\WithdrawalPayoutAttempt;
use App\Services\Settings\Qc168Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class Qc168Gateway
{
    private const CREATE_ORDER_ENDPOINT = '/octopus/api/order/new';

    private const GET_ORDER_ENDPOINT = '/octopus/api/order/get';

    public function __construct(
        private readonly Qc168Settings $settings,
    ) {}

    public function isAvailable(): bool
    {
        return $this->isDepositAvailable();
    }

    public function isDepositAvailable(): bool
    {
        return $this->masterEnabled()
            && $this->settings->depositEnabled()
            && $this->missingConfiguration('payment') === [];
    }

    public function isWithdrawalAvailable(): bool
    {
        return $this->masterEnabled()
            && $this->settings->withdrawEnabled()
            && $this->missingConfiguration('withdraw_payment') === [];
    }

    public function isQueryAvailable(): bool
    {
        return $this->masterEnabled()
            && $this->missingConfiguration(null) === [];
    }

    public function missingConfiguration(?string $paymentKey = 'payment'): array
    {
        $values = [
            'base_url' => config('services.qc168.base_url'),
            'oc_key' => config('services.qc168.oc_key'),
            'secret' => config('services.qc168.secret'),
            'merchant_uid' => config('services.qc168.merchant_uid'),
            'currency' => config('services.qc168.currency'),
            'country' => config('services.qc168.country'),
        ];

        if ($paymentKey !== null) {
            $values[$paymentKey] = $this->paymentChannel($paymentKey);
        }

        return collect($values)
            ->filter(fn (mixed $value): bool => blank($value))
            ->keys()
            ->values()
            ->all();
    }

    public function createDepositOrder(RechargeRequest $rechargeRequest): array
    {
        if (! $this->isDepositAvailable()) {
            throw new Qc168GatewayException('QC168 deposit is disabled or incompletely configured.');
        }

        $rechargeRequest->loadMissing('user');

        $payload = [
            'transaction_type' => 'income',
            'amount' => $this->formatAmount($rechargeRequest->amount),
            'merchant_order_id' => $rechargeRequest->merchant_order_id,
            'merchant_uid' => (string) config('services.qc168.merchant_uid'),
            'currency' => strtolower((string) config('services.qc168.currency', 'vnd')),
            'payment' => $this->paymentChannel('payment'),
            'redirect_url' => route('recharge.gateway.return', $rechargeRequest),
            'notify_url' => route('payment-gateway.qc168.callback'),
            'response_type' => 'json',
            'country' => strtolower((string) config('services.qc168.country', 'vn')),
            'accounting_type' => 'recharge',
            'from_name' => (string) ($rechargeRequest->user?->username ?: $rechargeRequest->user_id),
        ];

        $this->assertProductionUrl($payload['redirect_url'], 'redirect_url');
        $this->assertProductionUrl($payload['notify_url'], 'notify_url');

        $response = $this->request(self::CREATE_ORDER_ENDPOINT, $payload);
        $paymentUrl = data_get($response, 'data.payment_url');

        if (! is_string($paymentUrl) || ! filter_var($paymentUrl, FILTER_VALIDATE_URL)) {
            throw new Qc168GatewayException('QC168 did not return a valid payment URL.');
        }

        if (app()->environment('production') && parse_url($paymentUrl, PHP_URL_SCHEME) !== 'https') {
            throw new Qc168GatewayException('QC168 returned a non-HTTPS payment URL.');
        }

        return $response;
    }

    public function createPayoutOrder(WithdrawalPayoutAttempt $attempt): array
    {
        if (! $this->isWithdrawalAvailable()) {
            throw new Qc168GatewayException('QC168 withdrawal is disabled or incompletely configured.');
        }

        if (! preg_match('/^\d+$/', (string) $attempt->bank_code_snapshot)) {
            throw new Qc168GatewayException('The payout bank code must be numeric.');
        }

        $payload = [
            'transaction_type' => 'expense',
            'amount' => $this->formatAmount($attempt->amount),
            'merchant_order_id' => $attempt->merchant_order_id,
            'merchant_uid' => (string) config('services.qc168.merchant_uid'),
            'currency' => strtolower((string) config('services.qc168.currency', 'vnd')),
            'payment' => $this->paymentChannel('withdraw_payment'),
            'country' => strtolower((string) config('services.qc168.country', 'vn')),
            'accounting_type' => 'pay',
            'response_type' => 'json',
            'notify_url' => route('payment-gateway.qc168.withdrawal.callback'),
            'to' => $attempt->bank_account_no_snapshot,
            'to_name' => $attempt->bank_account_name_snapshot,
            'to_bank_name' => $attempt->bank_code_snapshot,
        ];

        $this->assertProductionUrl($payload['notify_url'], 'notify_url');

        return $this->request(self::CREATE_ORDER_ENDPOINT, $payload, 'withdraw_payment');
    }

    public function getOrder(string $merchantOrderId): array
    {
        return $this->request(self::GET_ORDER_ENDPOINT, [
            'merchant_order_id' => $merchantOrderId,
        ], null);
    }

    public function sign(array $payload): string
    {
        $secret = (string) config('services.qc168.secret');

        if ($secret === '') {
            throw new Qc168GatewayException('QC168 signing secret is not configured.');
        }

        return hash_hmac('sha256', $this->canonicalString($payload), $secret);
    }

    public function verifySignature(array $payload, ?string $receivedSignature): bool
    {
        $receivedSignature = strtolower(trim((string) $receivedSignature));

        if (! preg_match('/^[a-f0-9]{64}$/', $receivedSignature)) {
            return false;
        }

        try {
            return hash_equals($this->sign($payload), $receivedSignature);
        } catch (Qc168GatewayException|InvalidArgumentException) {
            return false;
        }
    }

    public function canonicalString(array $payload): string
    {
        ksort($payload, SORT_STRING);

        $parts = [];

        foreach ($payload as $key => $value) {
            $parts[] = (string) $key.'='.$this->signatureValue($value);
        }

        return implode('&', $parts);
    }

    public function formatAmount(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    public function request(string $endpoint, array $payload, ?string $paymentKey = 'payment'): array
    {
        if (! $this->masterEnabled()) {
            throw new Qc168GatewayException('QC168 payment gateway is disabled.');
        }

        $missing = $this->missingConfiguration($paymentKey);

        if ($missing !== []) {
            Log::warning('QC168 configuration is incomplete.', ['missing' => $missing]);

            throw new Qc168GatewayException('QC168 payment gateway configuration is incomplete.');
        }

        $baseUrl = rtrim((string) config('services.qc168.base_url'), '/');
        $this->assertProductionUrl($baseUrl, 'base_url');

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout((int) config('services.qc168.timeout', 20))
                ->connectTimeout((int) config('services.qc168.connect_timeout', 10))
                ->withHeaders([
                    'OC-Key' => (string) config('services.qc168.oc_key'),
                    'OC-Signature' => $this->sign($payload),
                ])
                ->post($baseUrl.$endpoint, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('QC168 request failed to connect.', ['endpoint' => $endpoint]);

            throw new Qc168UncertainSubmissionException('Unable to connect to QC168.', previous: $exception);
        }

        if (! $response->successful()) {
            Log::warning('QC168 returned a non-success HTTP response.', [
                'endpoint' => $endpoint,
                'http_status' => $response->status(),
            ]);

            $exceptionClass = $response->serverError()
                ? Qc168UncertainSubmissionException::class
                : Qc168GatewayException::class;

            throw new $exceptionClass('QC168 returned an unsuccessful HTTP response.');
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new Qc168UncertainSubmissionException('QC168 returned invalid JSON.');
        }

        if (($data['result'] ?? null) !== 'success') {
            Log::warning('QC168 rejected a request.', [
                'endpoint' => $endpoint,
                'result' => $data['result'] ?? null,
            ]);

            throw new Qc168GatewayException($this->providerErrorMessage($data));
        }

        return $data;
    }

    private function signatureValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        throw new InvalidArgumentException('QC168 signatures only support scalar payload values.');
    }

    private function providerErrorMessage(array $response): string
    {
        $message = data_get($response, 'data.error')
            ?? data_get($response, 'data.details')
            ?? data_get($response, 'data');

        return is_string($message) && $message !== ''
            ? 'QC168 rejected the request: '.mb_substr($message, 0, 300)
            : 'QC168 rejected the request.';
    }

    private function masterEnabled(): bool
    {
        return filter_var(
            config('services.qc168.enabled', false),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    private function paymentChannel(string $key): string
    {
        $value = config("services.qc168.{$key}") ?: config('services.qc168.payment');

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function assertProductionUrl(string $url, string $field): void
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Qc168GatewayException("QC168 {$field} is not a valid absolute URL.");
        }

        if (app()->environment('production') && parse_url($url, PHP_URL_SCHEME) !== 'https') {
            throw new Qc168GatewayException("QC168 {$field} must use HTTPS in production.");
        }
    }
}
