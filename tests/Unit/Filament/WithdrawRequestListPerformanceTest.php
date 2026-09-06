<?php

namespace Tests\Unit\Filament;

use App\Filament\Resources\WithdrawRequests\Pages\ListWithdrawRequests;
use ReflectionMethod;
use Tests\TestCase;

class WithdrawRequestListPerformanceTest extends TestCase
{
    public function test_withdrawal_list_uses_a_conservative_polling_interval(): void
    {
        $method = new ReflectionMethod(ListWithdrawRequests::class, 'getTablePollingInterval');

        $this->assertSame('60s', $method->invoke(new ListWithdrawRequests));
    }
}
