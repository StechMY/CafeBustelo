<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AdminTopUpService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Throwable;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('topUp')
                ->label('Top Up')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->form([
                    Select::make('wallet_type')
                        ->label('Wallet')
                        ->required()
                        ->default(Wallet::TYPE_RECHARGE)
                        ->options([
                            Wallet::TYPE_RECHARGE => 'Recharge Wallet',
                            Wallet::TYPE_WITHDRAWABLE => 'Withdrawable Wallet',
                        ])
                        ->native(false),

                    TextInput::make('amount')
                        ->label('Amount')
                        ->prefix(config('app.currency'))
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->step('0.01'),

                    Checkbox::make('counts_as_recharge')
                        ->label('Count as accumulated recharge'),

                    TextInput::make('remarks')
                        ->label('Remarks')
                        ->maxLength(255),
                ])
                ->requiresConfirmation()
                ->action(function (array $data): void {
                    try {
                        app(AdminTopUpService::class)->topUp(
                            user: $this->record,
                            adminUser: Filament::auth()->user(),
                            walletType: $data['wallet_type'],
                            amount: $data['amount'],
                            countsAsRecharge: (bool) ($data['counts_as_recharge'] ?? false),
                            remarks: $data['remarks'] ?? null,
                        );

                        Notification::make()
                            ->title('Top-up completed')
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title('Top-up failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('viewTeam')
                ->label('Team')
                ->icon('heroicon-o-users')
                ->modalHeading(fn(User $record): string => 'Team of ' . $record->username)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn(User $record) => view('filament.resources.users.components.team-tree', [
                    'record' => $record,
                    'downlineUsers' => $record->allDownlineUsers(),
                ]))
        ];
    }
}