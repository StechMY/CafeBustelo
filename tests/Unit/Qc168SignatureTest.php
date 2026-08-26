<?php

namespace Tests\Unit;

use App\Services\PaymentGateway\Qc168Gateway;
use Tests\TestCase;

class Qc168SignatureTest extends TestCase
{
    public function test_signature_is_order_independent_and_preserves_empty_and_zero_values(): void
    {
        config(['services.qc168.secret' => 'test-secret']);
        $gateway = app(Qc168Gateway::class);

        $payload = [
            'signture_key' => 'callback-key',
            'payment_fail_reason' => '',
            'amount' => '100.00',
            'zero_string' => '0',
            'zero_integer' => 0,
        ];
        $expectedCanonical = 'amount=100.00&payment_fail_reason=&signture_key=callback-key&zero_integer=0&zero_string=0';
        $signature = hash_hmac('sha256', $expectedCanonical, 'test-secret');

        $this->assertSame($expectedCanonical, $gateway->canonicalString($payload));
        $this->assertSame($signature, $gateway->sign($payload));
        $this->assertTrue($gateway->verifySignature(array_reverse($payload, true), strtoupper($signature)));
    }

    public function test_modified_or_incomplete_callback_payload_fails_verification(): void
    {
        config(['services.qc168.secret' => 'test-secret']);
        $gateway = app(Qc168Gateway::class);
        $payload = [
            'amount' => '100.00',
            'payment_fail_reason' => '',
            'signture_key' => 'callback-key',
        ];
        $signature = $gateway->sign($payload);

        $this->assertFalse($gateway->verifySignature([...$payload, 'amount' => '200.00'], $signature));

        unset($payload['signture_key']);
        $this->assertFalse($gateway->verifySignature($payload, $signature));
        $this->assertFalse($gateway->verifySignature($payload, 'not-a-signature'));
    }
}
