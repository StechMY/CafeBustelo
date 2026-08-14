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

        $hasNewRequest = false;

        if ($rechargeCount > $this->lastRechargeCount) {
            $hasNewRequest = true;

            Notification::make()
                ->title('New recharge request')
                ->body(($rechargeCount - $this->lastRechargeCount) . ' new pending recharge request(s).')
                ->warning()
                ->persistent()
                ->send();
        }

        if ($withdrawCount > $this->lastWithdrawCount) {
            $hasNewRequest = true;

            Notification::make()
                ->title('New withdraw request')
                ->body(($withdrawCount - $this->lastWithdrawCount) . ' new pending withdraw request(s).')
                ->danger()
                ->persistent()
                ->send();
        }

        if ($hasNewRequest) {
            $this->dispatch('admin-request-alert');
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

<div wire:poll.10s="poll">
    @once
        <div
            wire:ignore
            x-data="{
                soundEnabled: localStorage.getItem('adminAlertSoundEnabled') === '1',

                async playSound() {
                    const sound = this.$refs.sound;

                    if (!sound) return false;

                    try {
                        sound.volume = 1;
                        sound.muted = false;
                        sound.currentTime = 0;

                        await sound.play();

                        return true;
                    } catch (error) {
                        console.warn('Admin alert sound failed:', error);

                        return false;
                    }
                },

                async enableSound() {
                    if (!await this.playSound()) {
                        alert('Browser blocked sound. Please click again or allow sound for this site.');

                        return;
                    }

                    this.soundEnabled = true;
                    localStorage.setItem('adminAlertSoundEnabled', '1');
                },

                async playAlert() {
                    if (!this.soundEnabled) return;

                    if (await this.playSound()) return;

                    this.soundEnabled = false;
                    localStorage.removeItem('adminAlertSoundEnabled');
                },
            }"
            x-on:admin-request-alert.window="playAlert()"
        >
            <button
                x-cloak
                x-show="!soundEnabled"
                x-on:click="enableSound()"
                type="button"
                id="enable-admin-alert-sound"
                style="position: fixed; right: 16px; bottom: 16px; z-index: 9999;"
                class="rounded-lg bg-primary-600 px-3 py-2 text-xs font-bold text-white shadow"
            >
                🔊 Turn On Notification Sound
            </button>

            <audio x-ref="sound" id="admin-request-alert-sound" preload="auto">
                <source src="{{ asset('sounds/notification.wav') }}" type="audio/wav">
            </audio>
        </div>
    @endonce
</div>
