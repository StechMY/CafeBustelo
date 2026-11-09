<?php

namespace App\Services\PaymentGateway;

use App\Services\Settings\YdPaySettings;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class YdPayGateway
{
    public function isAvailable(string $business = 'deposit'): bool
    {
        try {
            $this->outboundProxy();
        } catch (RuntimeException) {
            return false;
        }

        return (bool) config('services.ydpay.enabled')
            && app(YdPaySettings::class)->enabled($business)
            && filled(config('services.ydpay.merchant_id'))
            && filled(config('services.ydpay.secret'))
            && filled(config("services.ydpay.{$business}_gateway_id"));
    }

    public function sign(array $payload): string
    {
        $secret = (string) config('services.ydpay.secret');
        if ($secret === '') {
            throw new RuntimeException('YDPAY signing secret is not configured.');
        }

        $parts = [];
        foreach ($payload as $key => $value) {
            if ($key === 'sign' || $value === null || $value === '') {
                continue;
            }
            if (is_array($value)) {
                ksort($value, SORT_STRING);
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (! is_scalar($value)) {
                throw new InvalidArgumentException('Invalid YDPAY signature value.');
            }
            $parts[] = $key.'='.$value;
        }
        usort($parts, 'strcasecmp');

        return strtoupper(md5(implode('&', $parts).'&key='.$secret));
    }

    public function verify(array $payload): bool
    {
        $signature = $payload['sign'] ?? null;
        if (! is_string($signature) || ! preg_match('/^[A-Fa-f0-9]{32}$/D', $signature)) {
            return false;
        }
        try {
            return hash_equals($this->sign($payload), strtoupper($signature));
        } catch (RuntimeException|InvalidArgumentException) {
            return false;
        }
    }

    /** VND still uses the provider's x100 units. Avoid floating-point conversion. */
    public function minorAmount(string $amount): string
    {
        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $amount, $matches)) {
            throw new InvalidArgumentException('Invalid YDPAY amount.');
        }
        $minor = ltrim($matches[1].str_pad($matches[2] ?? '', 2, '0'), '0');
        if ($minor === '') {
            throw new InvalidArgumentException('YDPAY amount must be positive.');
        }

        return $minor;
    }

    public function createDeposit(array $payload): array
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('YDPAY deposit is disabled or incompletely configured.');
        }

        return $this->request('/api/pay/create_order', $payload);
    }

    public function publicUrl(string $route, mixed $parameters = []): string
    {
        $url = rtrim((string) config('app.url'), '/').route($route, $parameters, false);
        if (! filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https' || strlen($url) > 128) {
            throw new RuntimeException('YDPAY requires a configured public HTTPS APP_URL and URLs up to 128 characters.');
        }

        return $url;
    }

    public function createPayout(array $payload): array
    {
        if (! $this->isAvailable('withdraw')) {
            throw new RuntimeException('YDPAY withdrawal is disabled or incompletely configured.');
        }

        return $this->request('/api/agentpay/apply', $payload);
    }

    public function query(string $merchantOrderId, string $business): array
    {
        $endpoint = match ($business) {
            'deposit' => '/api/pay/query_order',
            'withdraw' => '/api/agentpay/query_order',
            default => throw new InvalidArgumentException('Invalid YDPAY business.'),
        };

        return $this->request($endpoint, ['mchOrderNo' => $merchantOrderId, 'executeNotify' => 'false']);
    }

    public function queryBalance(): array
    {
        return $this->request('/api/agentpay/query_balance', []);
    }

    private function request(string $endpoint, array $payload): array
    {
        $proxy = $this->outboundProxy();
        $baseUrl = rtrim((string) config('services.ydpay.base_url'), '/');
        if (! config('services.ydpay.enabled') || ! filled(config('services.ydpay.merchant_id'))
            || ! filter_var($baseUrl, FILTER_VALIDATE_URL) || parse_url($baseUrl, PHP_URL_SCHEME) !== 'https') {
            throw new RuntimeException('YDPAY is disabled or incompletely configured.');
        }
        $payload = array_merge($payload, [
            'mchId' => (string) config('services.ydpay.merchant_id'),
            'version' => '1.0',
            'reqTime' => now('Asia/Shanghai')->format('YmdHis'),
        ]);
        $payload['sign'] = $this->sign($payload);

        // Never retry a create request: an ambiguous response must query the original order.
        try {
            $response = Http::asForm()->acceptJson()
                ->connectTimeout(10)->timeout(20)
                ->withOptions(['proxy' => $proxy, 'verify' => true, 'allow_redirects' => false, 'http_errors' => false])
                ->post($baseUrl.$endpoint, $payload);
        } catch (Throwable) {
            // Transport exceptions may contain proxy credentials. Never retry directly.
            throw new RuntimeException('YDPAY transport failed; query the original order through the configured proxy.');
        }
        $data = $response->json();
        if (! $response->successful() || ! is_array($data)
            || (string) ($data['retCode'] ?? '') !== '0' || ! $this->verify($data)) {
            // Do not log response bodies, request signatures or credentials.
            throw new RuntimeException('YDPAY result is unconfirmed; query the original order.');
        }

        return $data;
    }

    private function outboundProxy(): string
    {
        $proxy = config('third_party.outbound_proxy');
        $parts = is_string($proxy) ? parse_url($proxy) : false;
        if (! is_string($proxy) || preg_match('/[\x00-\x20\x7f]/', $proxy)
            || ! is_array($parts) || ! isset($parts['scheme'], $parts['host'])
            || ! in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || $parts['host'] === '' || ! in_array($parts['path'] ?? '', ['', '/'], true)
            || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] < 1)) {
            throw new RuntimeException('YDPAY requires a valid THIRD_PARTY_OUTBOUND_PROXY URL.');
        }

        return $proxy;
    }
}
