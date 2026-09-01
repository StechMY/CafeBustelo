<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.user'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('username')
                                    ->label(__('filament.username'))
                                    ->weight('bold')
                                    ->copyable(),

                                TextEntry::make('name')
                                    ->label(__('filament.name')),

                                TextEntry::make('phone')
                                    ->label(__('filament.phone'))
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('referral_code')
                                    ->label(__('filament.referral_code'))
                                    ->badge()
                                    ->copyable()
                                    ->color('info'),

                                TextEntry::make('referrer.username')
                                    ->label(__('filament.referred_by'))
                                    ->placeholder('-')
                                    ->url(fn(User $record): ?string => $record->referrer
                                        ? route('filament.admin.resources.users.view', $record->referrer)
                                        : null),

                                TextEntry::make('status')
                                    ->label(__('filament.status'))
                                    ->badge()
                                    ->color(fn(?string $state): string => match ($state) {
                                        'active' => 'success',
                                        'inactive' => 'gray',
                                        'suspended' => 'danger',
                                        default => 'warning',
                                    }),
                            ]),
                    ]),

                Section::make(__('filament.controls'))
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('is_virtual')
                                    ->label(__('filament.virtual'))
                                    ->formatStateUsing(fn(bool $state): string => $state ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'warning' : 'gray'),

                                TextEntry::make('order_status')
                                    ->label(__('filament.can_buy'))
                                    ->formatStateUsing(fn(bool $state): string => $state ? __('filament.enabled') : 'Disabled')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),

                                TextEntry::make('withdraw_status')
                                    ->label(__('filament.can_withdraw'))
                                    ->formatStateUsing(fn(bool $state): string => $state ? __('filament.enabled') : 'Disabled')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),

                                TextEntry::make('check_in_status')
                                    ->label(__('filament.can_check_in_2'))
                                    ->formatStateUsing(fn(bool $state): string => $state ? __('filament.enabled') : 'Disabled')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),

                                TextEntry::make('weekly_salary_status')
                                    ->label(__('filament.weekly_salary_claim'))
                                    ->formatStateUsing(fn(bool $state): string => $state ? __('filament.enabled') : 'Disabled')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),
                                TextEntry::make('monthly_salary_status')
                                    ->label(__('filament.monthly_salary_claim'))
                                    ->formatStateUsing(fn(bool $state): string => $state ? __('filament.enabled') : 'Disabled')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),
                                TextEntry::make('annual_salary_status')
                                    ->label(__('filament.annual_salary_claim'))
                                    ->formatStateUsing(fn(bool $state): string => $state ? __('filament.enabled') : 'Disabled')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),

                                TextEntry::make('accumulated_recharge')
                                    ->label(__('filament.accumulated_recharge'))
                                    ->prefix(config('app.currency') . " "),
                            ]),
                    ]),

                Section::make(__('filament.wallet_summary'))
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('recharge_wallet_balance')
                                    ->label(__('filament.recharge_wallet'))
                                    ->state(fn(User $record): float => $record->recharge_wallet_balance)
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('withdrawable_wallet_balance')
                                    ->label(__('filament.withdrawable_wallet'))
                                    ->state(fn(User $record): float => $record->withdrawable_wallet_balance)
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('total_approved_recharge')
                                    ->label(__('filament.approved_recharge'))
                                    ->state(fn(User $record): float => $record->total_approved_recharge)
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('total_approved_withdraw')
                                    ->label(__('filament.approved_withdraw'))
                                    ->state(fn(User $record): float => $record->total_approved_withdraw)
                                    ->prefix(config('app.currency') . " "),
                            ]),
                    ]),

                Section::make(__('filament.income_summary'))
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_daily_income')
                                    ->label(__('filament.daily_income'))
                                    ->state(fn(User $record): float => $record->total_daily_income)
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('total_commission')
                                    ->label(__('filament.commission'))
                                    ->state(fn(User $record): float => $record->total_commission)
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('bonus_income')
                                    ->label(__('filament.bonus'))
                                    ->state(fn(User $record): float => (float) $record->bonusClaims()->sum('amount'))
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('salary_income')
                                    ->label(__('filament.salary_plus_subsidy'))
                                    ->state(fn(User $record): float => (float) $record->salaryClaims()->sum('total_amount'))
                                    ->prefix(config('app.currency') . " "),
                            ]),
                    ]),

                Section::make(__('filament.vip_level_summary'))
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('current_vip_level_number')
                                    ->label(__('filament.current_lv'))
                                    ->state(fn(User $record): string => __('filament.lv') . $record->current_vip_level_number)
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('current_vip_level_name')
                                    ->label(__('filament.level_name'))
                                    ->state(fn(User $record): string => $record->current_vip_level_name),

                                TextEntry::make('accumulated_recharge')
                                    ->label(__('filament.accumulated_recharge'))
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('active_team_size')
                                    ->label(__('filament.active_team_size'))
                                    ->state(fn(User $record): int => $record->activeTeamSize()),

                                TextEntry::make('vip_level_upgrade_times')
                                    ->label(__('filament.vip_level_upgrade_times'))
                                    ->state(fn(User $record): array => $record->vipLevelAchievements
                                        ->map(fn($achievement): string => "LV{$achievement->level}: {$achievement->reached_at->format('Y-m-d H:i:s')}")
                                        ->all())
                                    ->listWithLineBreaks()
                                    ->placeholder('-'),
                            ]),
                    ]),

                Section::make(__('filament.team'))
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('b_team_count')
                                    ->label(__('filament.b_team'))
                                    ->state(fn(User $record): int => $record->teamUsersByLevel(1)->count()),

                                TextEntry::make('c_team_count')
                                    ->label(__('filament.c_team'))
                                    ->state(fn(User $record): int => $record->teamUsersByLevel(2)->count()),

                                TextEntry::make('d_team_count')
                                    ->label(__('filament.d_team'))
                                    ->state(fn(User $record): int => $record->teamUsersByLevel(3)->count()),

                                TextEntry::make('active_team_size')
                                    ->label(__('filament.active_team_size'))
                                    ->state(fn(User $record): int => $record->activeTeamSize()),
                            ]),
                    ]),

                Section::make(__('filament.login_ip'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('registered_ip')
                                    ->label(__('filament.registered_ip'))
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('last_login_ip')
                                    ->label(__('filament.last_login_ip'))
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('last_login_at')
                                    ->label(__('filament.last_login_at'))
                                    ->dateTime()
                                    ->placeholder('-'),
                            ]),
                    ]),

                Section::make(__('filament.timestamps'))
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('filament.created_at'))
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->label(__('filament.updated_at'))
                                    ->dateTime(),

                                TextEntry::make('activated_at')
                                    ->label(__('filament.activated_at'))
                                    ->dateTime()
                                    ->placeholder('-'),
                            ]),
                    ]),
            ]);
    }
}
