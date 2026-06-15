<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PlatformFinanceChart;
use App\Filament\Widgets\PlatformOverviewStats;
use App\Filament\Widgets\PlatformRequestStats;
use App\Filament\Widgets\PlatformUserStats;
use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use App\Models\Setting;
use App\Services\PointService;
use App\Services\WelcomeBonusService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;


class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function getTitle(): string
    {
        return __('filament.admin_dashboard');
    }

    protected bool $persistsFiltersInSession = true;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.dashboard_filters'))
                    ->description(__('filament.filter_dashboard_statistics_by_date_range_and_virtual_users'))
                    ->schema([
                        DatePicker::make('startDate')
                            ->label(__('filament.start_date'))
                            ->default(now()->startOfMonth())
                            ->native(false),

                        DatePicker::make('endDate')
                            ->label(__('filament.end_date'))
                            ->default(now())
                            ->native(false),

                        Select::make('virtualMode')
                            ->label(__('filament.virtual_users'))
                            ->options([
                                'exclude_virtual' => __('filament.exclude_virtual_users'),
                                'include_all' => __('filament.include_all_users'),
                                'only_virtual' => __('filament.only_virtual_users'),
                            ])
                            ->default('exclude_virtual')
                            ->selectablePlaceholder(false),
                    ])
                    ->columns([
                        'default' => 3,
                        'md' => 3,
                    ])->columnSpan('full'),
            ]);
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }

    public function getWidgets(): array
    {
        return [
            PlatformOverviewStats::class,
            PlatformRequestStats::class,
            PlatformUserStats::class,
            PlatformFinanceChart::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('welcomeBonusSettings')
                ->label(__('filament.welcome_bonus_settings'))
                ->icon('heroicon-o-gift')
                ->color('warning')
                ->modalHeading(__('filament.welcome_bonus_settings'))
                ->form([
                    Toggle::make('enabled')
                        ->label(__('filament.enable_welcome_bonus'))
                        ->default(fn(): bool => Setting::bool(
                            WelcomeBonusService::SETTING_ENABLED,
                            false
                        )),

                    TextInput::make('amount')
                        ->label(__('filament.welcome_bonus_amount'))
                        ->required()
                        ->minValue(0)
                        ->step('0.01')
                        ->placeholder('70,000.00')
                        ->formatStateUsing(function ($state): string {
                            $amount = Setting::decimal(
                                WelcomeBonusService::SETTING_AMOUNT,
                                70000
                            );

                            return number_format($amount, 2, '.', ',');
                        })
                        ->dehydrateStateUsing(function ($state): string {
                            return str_replace(',', '', (string) $state);
                        })
                        ->rule('regex:/^\d{1,3}(,\d{3})*(\.\d{1,2})?$|^\d+(\.\d{1,2})?$/')
                        ->extraInputAttributes([
                            'inputmode' => 'decimal',
                        ]),
                ])
                ->action(function (array $data): void {
                    Setting::setValue(
                        WelcomeBonusService::SETTING_ENABLED,
                        (bool) ($data['enabled'] ?? false)
                    );

                    Setting::setValue(
                        WelcomeBonusService::SETTING_AMOUNT,
                        str_replace(',', '', (string) ($data['amount'] ?? 0))
                    );

                    Notification::make()
                        ->title(__('filament.welcome_bonus_settings_saved'))
                        ->success()
                        ->send();
                }),

            Action::make('pointsSettings')
                ->label(__('filament.points_settings'))
                ->icon('heroicon-o-star')
                ->color('warning')
                ->modalHeading(__('filament.points_settings'))
                ->form([
                    Toggle::make('points_enabled')
                        ->label(__('filament.points_enabled'))
                        ->default(fn(): bool => Setting::bool(PointService::SETTING_POINTS_ENABLED, false)),

                    Toggle::make('upgrade_day_points_enabled')
                        ->label(__('filament.upgrade_day_points_enabled'))
                        ->default(fn(): bool => Setting::bool(PointService::SETTING_UPGRADE_DAY_ENABLED, false)),

                    TextInput::make('upgrade_day_points_amount')
                        ->label(__('filament.upgrade_day_points_amount'))
                        ->numeric()
                        ->default(fn(): float => Setting::decimal(PointService::SETTING_UPGRADE_DAY_AMOUNT, 0))
                        ->minValue(0)
                        ->step('0.01'),

                    Toggle::make('invite_day_points_enabled')
                        ->label(__('filament.invite_day_points_enabled'))
                        ->default(fn(): bool => Setting::bool(PointService::SETTING_INVITE_DAY_ENABLED, false)),

                    TextInput::make('invite_day_points_amount')
                        ->label(__('filament.invite_day_points_amount'))
                        ->numeric()
                        ->default(fn(): float => Setting::decimal(PointService::SETTING_INVITE_DAY_AMOUNT, 0))
                        ->minValue(0)
                        ->step('0.01'),

                    Toggle::make('points_cash_exchange_enabled')
                        ->label(__('filament.points_cash_exchange_enabled'))
                        ->default(fn(): bool => Setting::bool(PointService::SETTING_REDEEM_ENABLED, false)),

                    TextInput::make('points_to_cash_rate')
                        ->label(__('filament.points_to_cash_rate'))
                        ->numeric()
                        ->default(fn(): float => Setting::decimal(PointService::SETTING_POINTS_TO_CASH_RATE, 100))
                        ->minValue(0.01)
                        ->step('0.01')
                        ->helperText(__('filament.points_to_cash_rate_help')),

                    TextInput::make('minimum_points_to_redeem')
                        ->label(__('filament.minimum_points_to_redeem'))
                        ->numeric()
                        ->default(fn(): float => Setting::decimal(PointService::SETTING_MINIMUM_REDEEM_POINTS, 0))
                        ->minValue(0)
                        ->step('0.01'),
                    CheckboxList::make('upgrade_day_points_weekdays')
                        ->label(__('filament.upgrade_day_points_weekdays'))
                        ->options(Product::weekdayOptions())
                        ->columns(4)
                        ->default(function (): array {
                            $value = Setting::getValue(PointService::SETTING_UPGRADE_DAY_WEEKDAYS);

                            return $value
                                ? (json_decode((string) $value, true) ?: [1, 2])
                                : [1, 2];
                        })
                        ->helperText(__('filament.upgrade_day_points_weekdays_help')),

                    CheckboxList::make('invite_day_points_weekdays')
                        ->label(__('filament.invite_day_points_weekdays'))
                        ->options(Product::weekdayOptions())
                        ->columns(4)
                        ->default(function (): array {
                            $value = Setting::getValue(PointService::SETTING_INVITE_DAY_WEEKDAYS);

                            return $value
                                ? (json_decode((string) $value, true) ?: [4, 5])
                                : [4, 5];
                        })
                        ->helperText(__('filament.invite_day_points_weekdays_help')),
                ])
                ->action(function (array $data): void {
                    Setting::setValue(PointService::SETTING_POINTS_ENABLED, (bool) ($data['points_enabled'] ?? false));
                    Setting::setValue(PointService::SETTING_UPGRADE_DAY_ENABLED, (bool) ($data['upgrade_day_points_enabled'] ?? false));
                    Setting::setValue(PointService::SETTING_UPGRADE_DAY_AMOUNT, $data['upgrade_day_points_amount'] ?? 0);
                    Setting::setValue(PointService::SETTING_INVITE_DAY_ENABLED, (bool) ($data['invite_day_points_enabled'] ?? false));
                    Setting::setValue(PointService::SETTING_INVITE_DAY_AMOUNT, $data['invite_day_points_amount'] ?? 0);
                    Setting::setValue(PointService::SETTING_REDEEM_ENABLED, (bool) ($data['points_cash_exchange_enabled'] ?? false));
                    Setting::setValue(PointService::SETTING_POINTS_TO_CASH_RATE, $data['points_to_cash_rate'] ?? 100);
                    Setting::setValue(PointService::SETTING_MINIMUM_REDEEM_POINTS, $data['minimum_points_to_redeem'] ?? 0);
                    Setting::setValue(
                        PointService::SETTING_UPGRADE_DAY_WEEKDAYS,
                        json_encode($data['upgrade_day_points_weekdays'] ?? [1, 2])
                    );

                    Setting::setValue(
                        PointService::SETTING_INVITE_DAY_WEEKDAYS,
                        json_encode($data['invite_day_points_weekdays'] ?? [4, 5])
                    );
                    Notification::make()
                        ->title(__('filament.points_settings_saved'))
                        ->success()
                        ->send();
                })
        ];
    }
}