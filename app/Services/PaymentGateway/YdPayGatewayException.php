<?php

namespace App\Services\PaymentGateway;

use RuntimeException;

class YdPayGatewayException extends RuntimeException
{
    public function __construct(public readonly array $diagnostic)
    {
        parent::__construct($diagnostic['message']);
    }
}
