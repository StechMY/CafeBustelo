<?php

namespace App\Filament\Pages;

use App\Filament\Actions\TreasureChestSettingsAction;
use App\Filament\Resources\GoldenEggPrizes\GoldenEggPrizeResource;
use App\Filament\Resources\PageImages\PageImageResource;
use App\Filament\Widgets\PlatformFinanceChart;
use App\Filament\Widgets\PlatformOperationStats;
use App\Filament\Widgets\PlatformOverviewStats;
use App\Filament\Widgets\PlatformRequestStats;
use App\Filament\Widgets\PlatformUserStats;
use App\Models\AdminUser;
use App\Models\PhoneOtp;
use App\Models\Product;
use App\Models\Setting;
use App\Services\GoldenEggService;
use App\Services\MaintenanceNoticeService;
use App\Services\PointService;
use App\Services\Settings\Qc168Settings;
use App\Services\WelcomeBonusService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected string $view = 'filament.dashboard.index';

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
                            ->default(now())
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
        ];
    }

    public function getWidgets(): array
    {
        return [
            PlatformOperationStats::class,
            PlatformOverviewStats::class,
            PlatformRequestStats::class,
            PlatformUserStats::class,
            PlatformFinanceChart::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        $systemActions = [
            Action::make('pageImages')
                ->label(__('page_images.images'))
                ->icon('heroicon-o-photo')
                ->color('info')
                ->url(fn (): string => PageImageResource::getUrl())
                ->visible(fn (): bool => PageImageResource::canViewAny()),
            Action::make('phoneOtpRecords')
                ->label(__('filament.phone_otp_records'))
                ->icon('heroicon-o-device-phone-mobile')
                ->color('gray')
                ->modalHeading(__('filament.phone_otp_records'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('filament.close'))
                ->slideOver()
                ->modalContent(function () {
                    return view('filament.dashboard.phone-otp-records', [
                        'records' => PhoneOtp::query()
                            ->latest('id')
                            ->limit(50)
                            ->get(),
                    ]);
                }),
            Action::make('appDownloadSettings')
                ->label(__('filament.app_download_settings'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->modalHeading(__('filament.app_download_settings'))
                ->form([
                    TextInput::make('app_download_url')
                        ->label(__('filament.app_download_url'))
                        ->url()
                        ->maxLength(1000)
                        ->placeholder('https://example.com/app.apk')
                        ->default(fn (): ?string => Setting::getValue('app_download_url'))
                        ->helperText(__('filament.app_download_url_help')),
                ])
                ->action(function (array $data): void {
                    Setting::setValue(
                        'app_download_url',
                        trim((string) ($data['app_download_url'] ?? ''))
                    );

                    Notification::make()
                        ->title(__('filament.app_download_settings_saved'))
                        ->success()
                        ->send();
                }),
            \App\Filament\Actions\YdPaySettingsAction::make(),
            Action::make('paymentGatewaySettings')
                ->hidden() // QC168 retired from the UI; retain legacy processing.
                ->label('QC168')
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->visible(fn (): bool => Filament::auth()->user()?->can(Qc168Settings::MANAGE_PERMISSION) ?? false)
                ->authorize(fn (): bool => Filament::auth()->user()?->can(Qc168Settings::MANAGE_PERMISSION) ?? false)
                ->modalHeading(__('filament.payment_gateway_settings'))
                ->fillForm(fn (): array => app(Qc168Settings::class)->formData())
                ->form([
                    Toggle::make('deposit_enabled')
                        ->label(__('filament.qc168_automatic_deposit'))
                        ->required(),

                    Toggle::make('withdraw_enabled')
                        ->label(__('filament.qc168_automatic_withdrawal'))
                        ->required(),

                    TextInput::make('withdraw_release_hours')
                        ->label(__('filament.qc168_withdraw_release_hours'))
                        ->helperText(__('filament.qc168_withdraw_release_hours_help'))
                        ->suffix(__('filament.hours'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(720)
                        ->step(1)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $adminUser = Filament::auth()->user();

                    abort_unless($adminUser instanceof AdminUser, 403);

                    app(Qc168Settings::class)->save(
                        data: $data,
                        actor: $adminUser,
                        ip: request()->ip(),
                    );

                    Notification::make()
                        ->title(__('filament.payment_gateway_settings_saved'))
                        ->success()
                        ->send();
                }),
            Action::make('maintenanceNoticeSettings')
                ->label(__('filament.maintenance_notice_settings'))
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning')
                ->modalHeading(__('filament.maintenance_notice_settings'))
                ->modalDescription(__('filament.maintenance_notice_settings_help'))
                ->form([
                    Toggle::make('enabled')
                        ->label(__('filament.enable_maintenance_notice'))
                        ->default(fn (): bool => Setting::bool(
                            MaintenanceNoticeService::SETTING_ENABLED,
                            false
                        )),

                    DateTimePicker::make('starts_at')
                        ->label(__('filament.maintenance_starts_at'))
                        ->required()
                        ->seconds(false)
                        ->native(false)
                        ->default(fn (): string => (string) Setting::getValue(
                            MaintenanceNoticeService::SETTING_STARTS_AT,
                            today()->setTime(19, 0)->format('Y-m-d H:i:s')
                        )),

                    DateTimePicker::make('ends_at')
                        ->label(__('filament.maintenance_ends_at'))
                        ->required()
                        ->after('starts_at')
                        ->seconds(false)
                        ->native(false)
                        ->default(fn (): string => (string) Setting::getValue(
                            MaintenanceNoticeService::SETTING_ENDS_AT,
                            today()->addDay()->startOfDay()->format('Y-m-d H:i:s')
                        )),
                ])
                ->action(function (array $data): void {
                    Setting::setValue(
                        MaintenanceNoticeService::SETTING_ENABLED,
                        (bool) ($data['enabled'] ?? false)
                    );
                    Setting::setValue(
                        MaintenanceNoticeService::SETTING_STARTS_AT,
                        $data['starts_at']
                    );
                    Setting::setValue(
                        MaintenanceNoticeService::SETTING_ENDS_AT,
                        $data['ends_at']
                    );
                    Setting::setValue(
                        MaintenanceNoticeService::SETTING_REVISION,
                        (string) Str::uuid()
                    );

                    Notification::make()
                        ->title(__('filament.maintenance_notice_settings_saved'))
                        ->success()
                        ->send();
                }),
        ];

        $activityActions = [
            TreasureChestSettingsAction::make('treasureChestSettings'),
            Action::make('goldenEggSettings')
                ->label(__('golden_egg.admin_title'))
                ->icon('heroicon-o-gift')
                ->color('warning')
                ->visible(fn (): bool => Filament::auth()->user()?->can('Manage:GoldenEgg') ?? false)
                ->authorize(fn (): bool => Filament::auth()->user()?->can('Manage:GoldenEgg') ?? false)
                ->modalHeading(__('golden_egg.admin_title'))
                ->modalWidth('lg')
                ->modalSubmitActionLabel(__('filament-actions::edit.single.modal.actions.save.label'))
                ->fillForm(fn (): array => ['enabled' => Setting::bool(GoldenEggService::SETTING_ENABLED)])
                ->schema([
                    Toggle::make('enabled')->label(__('golden_egg.enable_activity'))
                        ->helperText(__('golden_egg.enable_help')),
                ])
                ->extraModalFooterActions([
                    Action::make('manageGoldenEggPrizes')
                        ->label(__('golden_egg.manage_prizes'))
                        ->url(fn (): string => GoldenEggPrizeResource::getUrl())
                        ->visible(fn (): bool => GoldenEggPrizeResource::canViewAny())
                        ->color('gray'),
                ])
                ->action(function (array $data): void {
                    Setting::setValue(GoldenEggService::SETTING_ENABLED, (bool) $data['enabled']);
                    Notification::make()->title(__('golden_egg.settings_saved'))->success()->send();
                }),
            Action::make('welcomeBonusSettings')
                ->label(__('filament.welcome_bonus_settings'))
                ->icon('heroicon-o-gift')
                ->color('warning')
                ->modalHeading(__('filament.welcome_bonus_settings'))
                ->form([
                    Toggle::make('enabled')
                        ->label(__('filament.enable_welcome_bonus'))
                        ->default(fn (): bool => Setting::bool(
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
                        ->default(fn (): bool => Setting::bool(PointService::SETTING_POINTS_ENABLED, false)),

                    Toggle::make('upgrade_day_points_enabled')
                        ->label(__('filament.upgrade_day_points_enabled'))
                        ->default(fn (): bool => Setting::bool(PointService::SETTING_UPGRADE_DAY_ENABLED, false)),

                    TextInput::make('upgrade_day_points_amount')
                        ->label(__('filament.upgrade_day_points_amount'))
                        ->numeric()
                        ->default(fn (): float => Setting::decimal(PointService::SETTING_UPGRADE_DAY_AMOUNT, 0))
                        ->minValue(0)
                        ->step('0.01'),

                    Toggle::make('invite_day_points_enabled')
                        ->label(__('filament.invite_day_points_enabled'))
                        ->default(fn (): bool => Setting::bool(PointService::SETTING_INVITE_DAY_ENABLED, false)),
                        // ->helperText(__('filament.invite_day_first_purchase_help')),

                    TextInput::make('invite_day_points_amount')
                        ->label(__('filament.invite_day_points_amount'))
                        ->numeric()
                        ->default(fn (): float => Setting::decimal(PointService::SETTING_INVITE_DAY_AMOUNT, 0))
                        ->minValue(0)
                        ->step('0.01'),

                    Toggle::make('invite_day_require_first_deposit')
                        ->label(__('filament.invite_day_require_first_deposit'))
                        ->default(fn (): bool => Setting::bool(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, false))
                        ->helperText(__('filament.invite_day_require_first_deposit_help')),

                    TextInput::make('invite_day_first_deposit_min_amount')
                        ->label(__('filament.invite_day_first_deposit_min_amount'))
                        ->numeric()
                        ->default(fn (): float => Setting::decimal(PointService::SETTING_INVITE_DAY_FIRST_DEPOSIT_MIN_AMOUNT, 0))
                        ->minValue(0)
                        ->prefix(config('app.currency'))
                        ->step('0.01')
                        ->helperText(__('filament.invite_day_first_deposit_min_amount_help')),

                    Toggle::make('points_cash_exchange_enabled')
                        ->label(__('filament.points_cash_exchange_enabled'))
                        ->default(fn (): bool => Setting::bool(PointService::SETTING_REDEEM_ENABLED, false)),

                    TextInput::make('points_to_cash_rate')
                        ->label(__('filament.points_to_cash_rate'))
                        ->numeric()
                        ->default(fn (): float => Setting::decimal(PointService::SETTING_POINTS_TO_CASH_RATE, 100))
                        ->minValue(0.01)
                        ->step('0.01')
                        ->helperText(__('filament.points_to_cash_rate_help')),

                    TextInput::make('minimum_points_to_redeem')
                        ->label(__('filament.minimum_points_to_redeem'))
                        ->numeric()
                        ->default(fn (): float => Setting::decimal(PointService::SETTING_MINIMUM_REDEEM_POINTS, 0))
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
                    Setting::setValue(PointService::SETTING_INVITE_DAY_REQUIRE_FIRST_DEPOSIT, (bool) ($data['invite_day_require_first_deposit'] ?? false));
                    Setting::setValue(PointService::SETTING_INVITE_DAY_FIRST_DEPOSIT_MIN_AMOUNT, $data['invite_day_first_deposit_min_amount'] ?? 0);
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
                }),
        ];

        return [
            ActionGroup::make($systemActions)
                ->extraDropdownAttributes(['data-dashboard-settings-menu' => ''])
                ->label(__('filament.system_settings'))
                ->icon('heroicon-o-cog-6-tooth')
                ->color('gray')
                ->button()
                ->dropdownWidth('sm'),
            ActionGroup::make($activityActions)
                ->extraDropdownAttributes(['data-dashboard-settings-menu' => ''])
                ->label(__('filament.activities_and_rewards'))
                ->icon('heroicon-o-gift')
                ->color('warning')
                ->button()
                ->dropdownWidth('sm'),
        ];
    }
}
