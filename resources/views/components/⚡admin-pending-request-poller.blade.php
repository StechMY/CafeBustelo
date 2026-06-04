<?php

use App\Models\RechargeRequest;
use App\Models\WithdrawRequest;
use Filament\Notifications\Notification;
use Livewire\Component;

new class extends Component
{
    public int $lastRechargeCount = 0;

    public int $lastWithdrawCount = 0;

    public function mount(): void
    {
        $this->lastRechargeCount = $this->pendingRechargeCount();
        $this->lastWithdrawCount = $this->pendingWithdrawCount();
    }

    public function poll(): void
    {
        $rechargeCount = $this->pendingRechargeCount();
        $withdrawCount = $this->pendingWithdrawCount();

        if ($rechargeCount > $this->lastRechargeCount) {
            Notification::make()
                ->title('New recharge request')
                ->body(($rechargeCount - $this->lastRechargeCount) . ' new pending recharge request(s).')
                ->warning()
                ->persistent()
                ->send();
        }

        if ($withdrawCount > $this->lastWithdrawCount) {
            Notification::make()
                ->title('New withdraw request')
                ->body(($withdrawCount - $this->lastWithdrawCount) . ' new pending withdraw request(s).')
                ->danger()
                ->persistent()
                ->send();
        }

        $this->lastRechargeCount = $rechargeCount;
        $this->lastWithdrawCount = $withdrawCount;
    }

    private function pendingRechargeCount(): int
    {
        return RechargeRequest::query()
            ->where('status', 'pending')
            ->count();
    }

    private function pendingWithdrawCount(): int
    {
        return WithdrawRequest::query()
            ->where('status', 'pending')
            ->count();
    }
};

?>

<div wire:poll.10s="poll" class="hidden"></div>