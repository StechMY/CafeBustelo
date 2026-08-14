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
use Filament\Forms\Components\DatePicker;
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
use Illuminate\Support\Facades\URL;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')
                    ->label(__('filament.username'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->description(fn(User $record): string => ($record->name ? "{$record->name} · " : '')
                        . __('filament.register_date') . ': '
                        . ($record->created_at?->format('Y-m-d H:i') ?? '-')),

                TextColumn::make('phone')
                    ->label(__('filament.phone'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make('status')
                    ->label(__('filament.status'))
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
                    ->label(__('filament.referral_code'))
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('referrer.username')
                    ->label(__('filament.referred_by'))
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('recharge_wallet_balance')
                    ->label(__('filament.recharge_balance'))
                    ->state(fn(User $record): float => $record->recharge_wallet_balance)
                    ->prefix(config('app.currency') . " ")
                    ->sortable(false)
                    ->toggleable(),

                TextColumn::make('withdrawable_wallet_balance')
                    ->label(__('filament.withdrawable_balance'))
                    ->state(fn(User $record): float => $record->withdrawable_wallet_balance)
                    ->prefix(config('app.currency') . " ")
                    ->sortable(false)
                    ->toggleable(),

                TextColumn::make('total_commission')
                    ->label(__('filament.commission'))
                    ->state(fn(User $record): float => $record->total_commission)
                    ->prefix(config('app.currency') . " ")
                    ->sortable(false)
                    ->toggleable(),

                TextColumn::make('current_vip_level')
                    ->label(__('filament.lv'))
                    ->state(fn(User $record): string => __('filament.lv') . $record->current_vip_level_number)
                    ->badge()
                    ->color('info')
                    ->sortable(false),

                TextColumn::make('active_team_size')
                    ->label(__('filament.active_team'))
                    ->state(fn(User $record): int => $record->activeTeamSize())
                    ->sortable(false)
                    ->toggleable(),

                TextColumn::make('last_login_ip')
                    ->label(__('filament.last_ip'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('last_login_at')
                    ->label(__('filament.last_login'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                ToggleColumn::make('order_status')
                    ->label(__('filament.order'))
                    ->sortable()
                    ->onColor('success')
                    ->offColor('danger')
                    ->afterStateUpdated(function (User $record, bool $state): void {
                        Notification::make()
                            ->title(__('filament.order_status_updated'))
                            ->body($record->username . ' ' . ($state ? __('filament.can_now_buy_products') : __('filament.cannot_buy_products_now')))
                            ->success()
                            ->send();
                    }),

                ToggleColumn::make('withdraw_status')
                    ->label(__('filament.withdraw'))
                    ->sortable()
                    ->onColor('success')
                    ->offColor('danger')
                    ->afterStateUpdated(function (User $record, bool $state): void {
                        Notification::make()
                            ->title(__('filament.withdraw_status_updated'))
                            ->body($record->username . ' ' . ($state ? __('filament.can_now_withdraw') : __('filament.cannot_withdraw_now')))
                            ->success()
                            ->send();
                    }),
                ToggleColumn::make('check_in_status')
                    ->label(__('filament.check_in'))
                    ->sortable()
                    ->onColor('success')
                    ->offColor('danger')
                    ->afterStateUpdated(function (User $record, bool $state): void {
                        Notification::make()
                            ->title(__('filament.check_in_status_updated'))
                            ->body($record->username . ' ' . ($state ? __('filament.can_now_check_in') : __('filament.cannot_check_in_now')))
                            ->success()
                            ->send();
                    }),

                ToggleColumn::make('is_virtual')
                    ->label(__('filament.virtual'))
                    ->sortable()
                    ->onColor('warning')
                    ->offColor('gray')
                    ->afterStateUpdated(function (User $record, bool $state): void {
                        Notification::make()
                            ->title(__('filament.virtual_status_updated'))
                            ->body($record->username . ' ' . __('filament.is_now_marked_as') . ' ' . ($state ? __('filament.virtual') : __('filament.real')))
                            ->success()
                            ->send();
                    }),
                ToggleColumn::make('weekly_salary_status')
                    ->label(__('filament.weekly_salary'))
                    ->afterStateUpdated(function ($record, bool $state): void {
                        Notification::make()
                            ->title(__('filament.weekly_salary_status_updated'))
                            ->success()
                            ->send();
                    }),

                ToggleColumn::make('monthly_salary_status')
                    ->label(__('filament.monthly_salary'))
                    ->afterStateUpdated(function ($record, bool $state): void {
                        Notification::make()
                            ->title(__('filament.monthly_salary_status_updated'))
                            ->success()
                            ->send();
                    }),

                ToggleColumn::make('annual_salary_status')
                    ->label(__('filament.annual_salary'))
                    ->afterStateUpdated(function ($record, bool $state): void {
                        Notification::make()
                            ->title(__('filament.annual_salary_status_updated'))
                            ->success()
                            ->send();
                    }),

                TextColumn::make('accumulated_recharge')
                    ->label(__('filament.accum_recharge'))
                    ->prefix(config('app.currency') . " ")
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('filament.created'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => __('filament.active'),
                        'inactive' => __('filament.inactive'),
                        // 'suspended' => __('filament.suspended'),
                    ]),

                SelectFilter::make('is_virtual')
                    ->label(__('filament.virtual_user'))
                    ->options([
                        1 => __('filament.virtual'),
                        0 => __('filament.real'),
                    ]),

                Filter::make('register_date')
                    ->label(__('filament.register_date'))
                    ->form([
                        DatePicker::make('from')
                            ->label(__('filament.start_date')),
                        DatePicker::make('until')
                            ->label(__('filament.end_date')),
                    ])
                    ->query(fn(Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn(Builder $query, string $date): Builder => $query->whereDate('users.created_at', '>=', $date),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn(Builder $query, string $date): Builder => $query->whereDate('users.created_at', '<=', $date),
                        )),

                Filter::make('ip_address')
                    ->label(__('filament.search_ip_history'))
                    ->form([
                        TextInput::make('ip')
                            ->label(__('filament.ip_address'))
                            ->placeholder(__('filament.example_175_141_1_1')),
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
                Action::make('login_as_user')
                    ->label(__('filament.login_as_user'))
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('filament.login_as_user'))
                    ->modalDescription(fn(User $record): string => __('filament.login_as_user_confirm', [
                        'username' => $record->username,
                    ]))
                    ->modalSubmitActionLabel(__('filament.continue'))
                    ->url(fn(User $record): string => URL::temporarySignedRoute(
                        name: 'admin.users.impersonate',
                        expiration: now()->addMinutes(2),
                        parameters: ['user' => $record],
                    ))
                    ->openUrlInNewTab(),

                Action::make('toggleStatus')
                    ->label(fn(User $record): string => $record->status === 'active' ? __('filament.deactivate') : __('filament.activate'))
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
                            ->title(__('filament.user_status_updated'))
                            ->success()
                            ->send();
                    }),

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
                            ->prefix('RM')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step('0.01'),

                        Checkbox::make('counts_as_recharge')
                            ->label(__('filament.count_as_accumulated_recharge'))
                            ->helperText(__('filament.only_enable_this_for_real_recharge_top_up')),

                        TextInput::make('remarks')
                            ->label(__('filament.remarks'))
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
                BulkAction::make('giveBonusCode')
                    ->label(__('filament.give_bonus_code'))
                    ->icon('heroicon-o-gift')
                    ->color('warning')
                    ->form([
                        Select::make('type')
                            ->label(__('filament.type'))
                            ->required()
                            ->default(BonusCode::TYPE_ADMIN)
                            ->options([
                                BonusCode::TYPE_ADMIN => __('filament.admin'),
                                BonusCode::TYPE_INVITE => __('filament.invite'),
                                BonusCode::TYPE_UPGRADE => __('filament.upgrade'),
                                BonusCode::TYPE_COMPENSATION => __('filament.compensation'),
                            ])
                            ->native(false),

                        TextInput::make('amount')
                            ->label(__('filament.amount'))
                            ->prefix('RM')
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->step('0.01'),

                        DateTimePicker::make('expires_at')
                            ->label(__('filament.expires_at'))
                            ->seconds(false)
                            ->nullable(),

                        Textarea::make('remark')
                            ->label(__('filament.remark_reason'))
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
                            ->title(__('filament.bonus_codes_distributed'))
                            ->body(__('filament.users_received_bonus_codes', ['count' => $records->count()]))
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
