<?php

use App\Models\RechargeRequest;
use App\Models\BankAccountReviewRequest;
use App\Models\WithdrawRequest;
use App\Support\FilamentSupportScope;
use Filament\Notifications\Notification;
use Livewire\Component;

new class extends Component
{
    public int $lastRechargeCount = 0;

    public int $lastWithdrawCount = 0;

    public int $lastBankAccountReviewCount = 0;

    public function mount(): void
    {
        $this->lastRechargeCount = $this->pendingRechargeCount();
        $this->lastWithdrawCount = $this->pendingWithdrawCount();
        $this->lastBankAccountReviewCount = $this->pendingBankAccountReviewCount();
    }

    public function poll(): void
    {
        $rechargeCount = $this->pendingRechargeCount();
        $withdrawCount = $this->pendingWithdrawCount();
        $bankAccountReviewCount = $this->pendingBankAccountReviewCount();

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

        if ($bankAccountReviewCount > $this->lastBankAccountReviewCount) {
            $hasNewRequest = true;

            Notification::make()
                ->title('New bank account review request')
                ->body(($bankAccountReviewCount - $this->lastBankAccountReviewCount) . ' new pending bank account review request(s).')
                ->warning()
                ->persistent()
                ->send();
        }

        if ($hasNewRequest) {
            $this->dispatch('admin-request-alert');
        }

        $this->lastRechargeCount = $rechargeCount;
        $this->lastWithdrawCount = $withdrawCount;
        $this->lastBankAccountReviewCount = $bankAccountReviewCount;
    }

    private function pendingRechargeCount(): int
    {
        return FilamentSupportScope::applyToUserOwnedQuery(RechargeRequest::query())
            ->where('status', 'pending')
            ->count();
    }

    private function pendingWithdrawCount(): int
    {
        return FilamentSupportScope::applyToUserOwnedQuery(WithdrawRequest::query())
            ->where('status', 'pending')
            ->count();
    }

    private function pendingBankAccountReviewCount(): int
    {
        return FilamentSupportScope::applyToUserOwnedQuery(BankAccountReviewRequest::query())
            ->where('status', BankAccountReviewRequest::STATUS_PENDING)
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
                audioContext: null,

                init() {
                    const unlockSavedPreference = () => {
                        if (this.soundEnabled) this.unlockAudio();
                    };

                    window.addEventListener('pointerdown', unlockSavedPreference, { once: true, capture: true });
                    window.addEventListener('keydown', unlockSavedPreference, { once: true, capture: true });
                },

                async unlockAudio() {
                    try {
                        const AudioContext = window.AudioContext || window.webkitAudioContext;

                        if (!AudioContext) return false;

                        this.audioContext ??= new AudioContext();
                        await this.audioContext.resume();

                        return this.audioContext.state === 'running';
                    } catch (error) {
                        console.warn('Admin alert audio could not be unlocked:', error);

                        return false;
                    }
                },

                playSound() {
                    if (!this.audioContext || this.audioContext.state !== 'running') return false;

                    const now = this.audioContext.currentTime;

                    [
                        { frequency: 880, start: 0, duration: 0.16 },
                        { frequency: 1175, start: 0.20, duration: 0.22 },
                    ].forEach(({ frequency, start, duration }) => {
                        const oscillator = this.audioContext.createOscillator();
                        const gain = this.audioContext.createGain();

                        oscillator.type = 'sine';
                        oscillator.frequency.value = frequency;
                        gain.gain.setValueAtTime(0.0001, now + start);
                        gain.gain.exponentialRampToValueAtTime(0.35, now + start + 0.02);
                        gain.gain.exponentialRampToValueAtTime(0.0001, now + start + duration);
                        oscillator.connect(gain);
                        gain.connect(this.audioContext.destination);
                        oscillator.start(now + start);
                        oscillator.stop(now + start + duration);
                    });

                    return true;
                },

                async enableSound() {
                    if (!await this.unlockAudio() || !this.playSound()) {
                        alert('Browser blocked sound. Please allow audio for this site and try again.');

                        return;
                    }

                    this.soundEnabled = true;
                    localStorage.setItem('adminAlertSoundEnabled', '1');
                },

                playAlert() {
                    if (!this.soundEnabled) return;

                    if (this.playSound()) return;

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
        </div>
    @endonce
</div>
