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
                Section::make('User')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('username')
                                    ->label('Username')
                                    ->weight('bold')
                                    ->copyable(),

                                TextEntry::make('name')
                                    ->label('Name'),

                                TextEntry::make('phone')
                                    ->label('Phone')
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('referral_code')
                                    ->label('Referral Code')
                                    ->badge()
                                    ->copyable()
                                    ->color('info'),

                                TextEntry::make('referrer.username')
                                    ->label('Referred By')
                                    ->placeholder('-')
                                    ->url(fn(User $record): ?string => $record->referrer
                                        ? route('filament.admin.resources.users.view', $record->referrer)
                                        : null),

                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn(?string $state): string => match ($state) {
                                        'active' => 'success',
                                        'inactive' => 'gray',
                                        'suspended' => 'danger',
                                        default => 'warning',
                                    }),
                            ]),
                    ]),

                Section::make('Controls')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('is_virtual')
                                    ->label('Virtual')
                                    ->formatStateUsing(fn(bool $state): string => $state ? 'Yes' : 'No')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'warning' : 'gray'),

                                TextEntry::make('order_status')
                                    ->label('Can Buy')
                                    ->formatStateUsing(fn(bool $state): string => $state ? 'Enabled' : 'Disabled')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),

                                TextEntry::make('withdraw_status')
                                    ->label('Can Withdraw')
                                    ->formatStateUsing(fn(bool $state): string => $state ? 'Enabled' : 'Disabled')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),

                                TextEntry::make('check_in_status')
                                    ->label('Can Check In')
                                    ->formatStateUsing(fn(bool $state): string => $state ? 'Enabled' : 'Disabled')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),

                                TextEntry::make('weekly_salary_status')
                                    ->label('Weekly Salary Claim')
                                    ->formatStateUsing(fn(bool $state): string => $state ? 'Enabled' : 'Disabled')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),
                                TextEntry::make('monthly_salary_status')
                                    ->label('Monthly Salary Claim')
                                    ->formatStateUsing(fn(bool $state): string => $state ? 'Enabled' : 'Disabled')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),
                                TextEntry::make('annual_salary_status')
                                    ->label('Annual Salary Claim')
                                    ->formatStateUsing(fn(bool $state): string => $state ? 'Enabled' : 'Disabled')
                                    ->badge()
                                    ->color(fn(bool $state): string => $state ? 'success' : 'danger'),

                                TextEntry::make('accumulated_recharge')
                                    ->label('Accumulated Recharge')
                                    ->prefix(config('app.currency') . " "),
                            ]),
                    ]),

                Section::make('Wallet Summary')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('recharge_wallet_balance')
                                    ->label('Recharge Wallet')
                                    ->state(fn(User $record): float => $record->recharge_wallet_balance)
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('withdrawable_wallet_balance')
                                    ->label('Withdrawable Wallet')
                                    ->state(fn(User $record): float => $record->withdrawable_wallet_balance)
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('total_approved_recharge')
                                    ->label('Approved Recharge')
                                    ->state(fn(User $record): float => $record->total_approved_recharge)
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('total_approved_withdraw')
                                    ->label('Approved Withdraw')
                                    ->state(fn(User $record): float => $record->total_approved_withdraw)
                                    ->prefix(config('app.currency') . " "),
                            ]),
                    ]),

                Section::make('Income Summary')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('total_daily_income')
                                    ->label('Daily Income')
                                    ->state(fn(User $record): float => $record->total_daily_income)
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('total_commission')
                                    ->label('Commission')
                                    ->state(fn(User $record): float => $record->total_commission)
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('bonus_income')
                                    ->label('Bonus')
                                    ->state(fn(User $record): float => (float) $record->bonusClaims()->sum('amount'))
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('salary_income')
                                    ->label('Salary + Subsidy')
                                    ->state(fn(User $record): float => (float) $record->salaryClaims()->sum('total_amount'))
                                    ->prefix(config('app.currency') . " "),
                            ]),
                    ]),

                Section::make('VIP Level Summary')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('current_vip_level_number')
                                    ->label('Current LV')
                                    ->state(fn(User $record): string => 'LV' . $record->current_vip_level_number)
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('current_vip_level_name')
                                    ->label('Level Name')
                                    ->state(fn(User $record): string => $record->current_vip_level_name),

                                TextEntry::make('accumulated_recharge')
                                    ->label('Accumulated Recharge')
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('active_team_size')
                                    ->label('Active Team Size')
                                    ->state(fn(User $record): int => $record->activeTeamSize()),
                            ]),
                    ]),

                Section::make('Team')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextEntry::make('b_team_count')
                                    ->label('B Team')
                                    ->state(fn(User $record): int => $record->teamUsersByLevel(1)->count()),

                                TextEntry::make('c_team_count')
                                    ->label('C Team')
                                    ->state(fn(User $record): int => $record->teamUsersByLevel(2)->count()),

                                TextEntry::make('d_team_count')
                                    ->label('D Team')
                                    ->state(fn(User $record): int => $record->teamUsersByLevel(3)->count()),

                                TextEntry::make('active_team_size')
                                    ->label('Active Team Size')
                                    ->state(fn(User $record): int => $record->activeTeamSize()),
                            ]),
                    ]),

                Section::make('Login IP')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('registered_ip')
                                    ->label('Registered IP')
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('last_login_ip')
                                    ->label('Last Login IP')
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('last_login_at')
                                    ->label('Last Login At')
                                    ->dateTime()
                                    ->placeholder('-'),
                            ]),
                    ]),

                Section::make('Timestamps')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->dateTime(),

                                TextEntry::make('updated_at')
                                    ->dateTime(),

                                TextEntry::make('activated_at')
                                    ->dateTime()
                                    ->placeholder('-'),
                            ]),
                    ]),
            ]);
    }
}