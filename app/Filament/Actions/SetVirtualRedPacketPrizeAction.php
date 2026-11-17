<?php

namespace App\Filament\Actions;

use App\Models\RedPacketPrize;
use App\Models\RedPacketPrizeOverride;
use App\Models\User;
use App\Services\RedPacketService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class SetVirtualRedPacketPrizeAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->label(__('red_packet.set_next_prize'))->icon('heroicon-o-envelope')->color('danger')
            ->visible(fn (?User $record): bool => $this->canAssign($record))
            ->authorize(fn (?User $record): bool => $this->canAssign($record))
            ->modalHeading(fn (User $record): string => __('red_packet.set_next_prize').' · '.$record->username)
            ->modalDescription(__('red_packet.next_prize_help'))
            ->fillForm(fn (User $record): array => [
                'prize_id' => RedPacketPrizeOverride::where('user_id', $record->id)->pending()->value('prize_id'),
            ])->schema([
                Select::make('prize_id')->label(__('red_packet.prize'))->nullable()
                    ->options(fn (): array => RedPacketPrize::orderBy('slot')->pluck('name', 'id')->all())
                    ->placeholder(__('red_packet.random_prize'))->native(false),
            ])->action(function (User $record, array $data): void {
                app(RedPacketService::class)->setNextPrize(Filament::auth()->user(), $record,
                    filled($data['prize_id'] ?? null) ? (int) $data['prize_id'] : null);
                Notification::make()->title(__('red_packet.next_prize_saved'))->success()->send();
            });
    }

    private function canAssign(?User $record): bool
    {
        $admin = Filament::auth()->user();

        return $admin && $record && app(RedPacketService::class)->canSetNextPrize($admin, $record);
    }
}
