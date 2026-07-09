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
        <button
            type="button"
            id="enable-admin-alert-sound"
            style="position: fixed; right: 16px; bottom: 16px; z-index: 9999;"
            class="rounded-lg bg-primary-600 px-3 py-2 text-xs font-bold text-white shadow"
        >
            🔊 Turn On Notification Sound
        </button>

        <audio id="admin-request-alert-sound" preload="auto">
            <source src="{{ asset('sounds/notification.wav') }}" type="audio/wav">
        </audio>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const sound = document.getElementById('admin-request-alert-sound');
                const enableButton = document.getElementById('enable-admin-alert-sound');

                let soundEnabled = localStorage.getItem('adminAlertSoundEnabled') === '1';

                const refreshButton = () => {
                    if (!enableButton) return;

                    enableButton.style.display = soundEnabled ? 'none' : 'block';
                };

                const unlockSound = async () => {
                    if (!sound) {
                        console.warn('Admin alert sound element not found.');
                        return false;
                    }

                    try {
                        sound.volume = 1;
                        sound.muted = false;
                        sound.currentTime = 0;

                        await sound.play();

                        sound.pause();
                        sound.currentTime = 0;

                        soundEnabled = true;
                        localStorage.setItem('adminAlertSoundEnabled', '1');

                        refreshButton();

                        console.log('Admin alert sound enabled.');

                        return true;
                    } catch (error) {
                        console.warn('Sound unlock failed:', error);
                        alert('Browser blocked sound. Please click again or allow sound for this site.');
                        return false;
                    }
                };

                const playSound = async () => {
                    if (!sound) {
                        console.warn('Admin alert sound element not found.');
                        return;
                    }

                    if (!soundEnabled) {
                        console.warn('Admin alert sound is not enabled yet.');
                        refreshButton();
                        return;
                    }

                    try {
                        sound.volume = 1;
                        sound.muted = false;
                        sound.currentTime = 0;

                        await sound.play();

                        console.log('Admin alert sound played.');
                    } catch (error) {
                        console.warn('Sound play failed:', error);

                        soundEnabled = false;
                        localStorage.removeItem('adminAlertSoundEnabled');

                        refreshButton();
                    }
                };

                enableButton?.addEventListener('click', unlockSound);

                window.addEventListener('admin-request-alert', () => {
                    console.log('admin-request-alert event received.');
                    playSound();
                });

                refreshButton();
            });
        </script>
    @endonce
    
</div>