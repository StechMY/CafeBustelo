<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AdminTopUpService;
use App\Support\FilamentSupportScope;
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


    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_unless(FilamentSupportScope::canAccessUser($this->record), 403);
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('topUp')
                ->label(__('filament.top_up'))
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->form([
                    Select::make('wallet_type')
                        ->label(__('filament.wallet'))
                        ->required()
                        ->default(Wallet::TYPE_RECHARGE)
                        ->options([
                            Wallet::TYPE_RECHARGE => __('filament.recharge_wallet'),
                            Wallet::TYPE_WITHDRAWABLE => __('filament.withdrawable_wallet'),
                        ])
                        ->native(false),

                    TextInput::make('amount')
                        ->label(__('filament.amount'))
                        ->prefix(config('app.currency'))
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->step('0.01'),

                    Checkbox::make('counts_as_recharge')
                        ->label(__('filament.count_as_accumulated_recharge')),

                    TextInput::make('remarks')
                        ->label(__('filament.remarks'))
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
                            ->title(__('filament.top_up_completed'))
                            ->success()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->title(__('filament.top_up_failed'))
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('viewTeam')
                ->label(__('filament.team'))
                ->icon('heroicon-o-users')
                ->modalHeading(fn(User $record): string => __('filament.team_of') . $record->username)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('filament.close'))
                ->modalContent(fn(User $record) => view('filament.resources.users.components.team-tree', [
                    'record' => $record,
                    'downlineUsers' => $record->allDownlineUsers(),
                ]))
        ];
    }

}