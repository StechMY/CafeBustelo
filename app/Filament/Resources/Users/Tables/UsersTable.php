<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Models\Wallet;
use App\Services\AdminTopUpService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use App\Models\BonusCode;
use App\Services\BonusCodeService;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Enums\FiltersLayout;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->label('Username')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->description(fn(User $record): string => $record->name),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->color(fn(?string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'suspended' => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('referral_code')
                    ->label('Referral Code')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('referrer.username')
                    ->label('Referred By')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('recharge_wallet_balance')
                    ->label('Recharge Balance')
                    ->state(fn(User $record): float => $record->recharge_wallet_balance)
                    ->prefix(config('app.currency') . " ")
                    ->sortable(false)
                    ->toggleable(),

                TextColumn::make('withdrawable_wallet_balance')
                    ->label('Withdrawable Balance')
                    ->state(fn(User $record): float => $record->withdrawable_wallet_balance)
                    ->prefix(config('app.currency') . " ")
                    ->sortable(false)
                    ->toggleable(),

                TextColumn::make('total_commission')
                    ->label('Commission')
                    ->state(fn(User $record): float => $record->total_commission)
                    ->prefix(config('app.currency') . " ")
                    ->sortable(false)
                    ->toggleable(),

                TextColumn::make('current_vip_level')
                    ->label('LV')
                    ->state(fn(User $record): string => 'LV' . $record->current_vip_level_number)
                    ->badge()
                    ->color('info')
                    ->sortable(false),

                TextColumn::make('active_team_size')
                    ->label('Active Team')
                    ->state(fn(User $record): int => $record->activeTeamSize())
                    ->sortable(false)
                    ->toggleable(),

                TextColumn::make('last_login_ip')
                    ->label('Last IP')
                    ->searchable()
                    ->copyable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                ToggleColumn::make('order_status')
                    ->label('Order')
                    ->sortable()
                    ->onColor('success')
                    ->offColor('danger')
                    ->afterStateUpdated(function (User $record, bool $state): void {
                        Notification::make()
                            ->title('Order status updated')
                            ->body($record->username . ' ' . ($state ? 'can now buy products.' : 'cannot buy products now.'))
                            ->success()
                            ->send();
                    }),

                ToggleColumn::make('withdraw_status')
                    ->label('Withdraw')
                    ->sortable()
                    ->onColor('success')
                    ->offColor('danger')
                    ->afterStateUpdated(function (User $record, bool $state): void {
                        Notification::make()
                            ->title('Withdraw status updated')
                            ->body($record->username . ' ' . ($state ? 'can now withdraw.' : 'cannot withdraw now.'))
                            ->success()
                            ->send();
                    }),
                ToggleColumn::make('check_in_status')
                    ->label('Check-in')
                    ->sortable()
                    ->onColor('success')
                    ->offColor('danger')
                    ->afterStateUpdated(function (User $record, bool $state): void {
                        Notification::make()
                            ->title('Check in status updated')
                            ->body($record->username . ' ' . ($state ? 'can now check in.' : 'cannot check in now.'))
                            ->success()
                            ->send();
                    }),

                ToggleColumn::make('is_virtual')
                    ->label('Virtual')
                    ->sortable()
                    ->onColor('warning')
                    ->offColor('gray')
                    ->afterStateUpdated(function (User $record, bool $state): void {
                        Notification::make()
                            ->title('Virtual status updated')
                            ->body($record->username . ' is now marked as ' . ($state ? 'virtual.' : 'real.'))
                            ->success()
                            ->send();
                    }),
                ToggleColumn::make('weekly_salary_status')
                    ->label('Weekly Salary')
                    ->afterStateUpdated(function ($record, bool $state): void {
                        Notification::make()
                            ->title('Weekly salary status updated')
                            ->success()
                            ->send();
                    }),

                ToggleColumn::make('monthly_salary_status')
                    ->label('Monthly Salary')
                    ->afterStateUpdated(function ($record, bool $state): void {
                        Notification::make()
                            ->title('Monthly salary status updated')
                            ->success()
                            ->send();
                    }),

                ToggleColumn::make('annual_salary_status')
                    ->label('Annual Salary')
                    ->afterStateUpdated(function ($record, bool $state): void {
                        Notification::make()
                            ->title('Annual salary status updated')
                            ->success()
                            ->send();
                    }),

                TextColumn::make('accumulated_recharge')
                    ->label('Accum. Recharge')
                    ->prefix(config('app.currency') . " ")
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        // 'suspended' => 'Suspended',
                    ]),

                SelectFilter::make('is_virtual')
                    ->label('Virtual User')
                    ->options([
                        1 => 'Virtual',
                        0 => 'Real',
                    ]),

                Filter::make('ip_address')
                    ->label('Search IP History')
                    ->form([
                        TextInput::make('ip')
                            ->label('IP Address')
                            ->placeholder('Example: 175.141.1.1'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $ip = $data['ip'] ?? null;

                        if (blank($ip)) {
                            return $query;
                        }

                        return $query->where(function (Builder $query) use ($ip): void {
                            $query
                                ->where('last_login_ip', 'like', "%{$ip}%")
                                ->orWhere('registered_ip', 'like', "%{$ip}%")
                                ->orWhereHas('loginLogs', function (Builder $query) use ($ip): void {
                                    $query->where('ip_address', 'like', "%{$ip}%");
                                });
                        });
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                ViewAction::make(),

                EditAction::make(),

                Action::make('toggleStatus')
                    ->label(fn(User $record): string => $record->status === 'active' ? 'Deactivate' : 'Activate')
                    ->icon(fn(User $record): string => $record->status === 'active'
                        ? 'heroicon-o-no-symbol'
                        : 'heroicon-o-check-circle')
                    ->color(fn(User $record): string => $record->status === 'active' ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->forceFill([
                            'status' => $record->status === 'active' ? 'inactive' : 'active',
                        ])->save();

                        Notification::make()
                            ->title('User status updated')
                            ->success()
                            ->send();
                    }),

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
                            ->prefix('RM')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step('0.01'),

                        Checkbox::make('counts_as_recharge')
                            ->label('Count as accumulated recharge')
                            ->helperText('Only enable this for real recharge top-up.'),

                        TextInput::make('remarks')
                            ->label('Remarks')
                            ->maxLength(255),
                    ])
                    ->requiresConfirmation()
                    ->action(function (User $record, array $data): void {
                        try {
                            app(AdminTopUpService::class)->topUp(
                                user: $record,
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
                BulkAction::make('giveBonusCode')
                    ->label('Give Bonus Code')
                    ->icon('heroicon-o-gift')
                    ->color('warning')
                    ->form([
                        Select::make('type')
                            ->label('Type')
                            ->required()
                            ->default(BonusCode::TYPE_ADMIN)
                            ->options([
                                BonusCode::TYPE_ADMIN => 'Admin',
                                BonusCode::TYPE_INVITE => 'Invite',
                                BonusCode::TYPE_UPGRADE => 'Upgrade',
                                BonusCode::TYPE_COMPENSATION => 'Compensation',
                            ])
                            ->native(false),

                        TextInput::make('amount')
                            ->label('Amount')
                            ->prefix('RM')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step('0.01'),

                        DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->seconds(false)
                            ->nullable(),

                        Textarea::make('remark')
                            ->label('Remark / Reason')
                            ->rows(3),
                    ])
                    ->action(function (Collection $records, array $data): void {
                        DB::transaction(function () use ($records, $data): void {
                            foreach ($records as $user) {
                                BonusCode::query()->create([
                                    'code' => app(BonusCodeService::class)->generateUniqueCode($data['type']),
                                    'owner_user_id' => $user->id,
                                    'issued_by_admin_user_id' => Filament::auth()->id(),
                                    'type' => $data['type'],
                                    'amount' => $data['amount'],
                                    'status' => BonusCode::STATUS_UNUSED,
                                    'expires_at' => $data['expires_at'] ?? null,
                                    'remark' => $data['remark'] ?? null,
                                ]);
                            }
                        });

                        Notification::make()
                            ->title('Bonus codes distributed')
                            ->body($records->count() . ' user(s) received bonus codes.')
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}