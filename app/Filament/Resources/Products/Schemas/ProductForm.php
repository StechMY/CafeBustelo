<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Models\VipLevel;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.product_information'))
                    ->description(__('filament.basic_product_package_information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament.product_name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, callable $set): void {
                                        if (blank($state)) {
                                            return;
                                        }

                                        $set('code', Str::upper(Str::slug($state, '_')));
                                    }),

                                TextInput::make('code')
                                    ->label(__('filament.product_code'))
                                    ->required()
                                    ->maxLength(32)
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash()
                                    ->dehydrateStateUsing(fn(?string $state): ?string => $state ? Str::upper($state) : null),

                                Select::make('product_type')
                                    ->label(__('filament.product_type'))
                                    ->options(Product::typeOptions())
                                    ->default(Product::TYPE_NORMAL)
                                    ->required()
                                    ->native(false),

                                Select::make('status')
                                    ->label(__('filament.status'))
                                    ->required()
                                    ->default('active')
                                    ->options([
                                        'active' => __('filament.active'),
                                        'inactive' => __('filament.inactive'),
                                    ])
                                    ->native(false),

                                TextInput::make('sort_order')
                                    ->label(__('filament.sort_order'))
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),

                                CheckboxList::make('available_days')
                                    ->label(__('filament.available_days'))
                                    ->options(Product::weekdayOptions())
                                    ->columns(4)
                                    ->columnSpanFull()
                                    ->helperText(__('filament.available_days_help')),
                            ]),
                    ]),

                Section::make(__('filament.product_image'))
                    ->description(__('filament.uploaded_to_digitalocean_spaces'))
                    ->schema([
                        FileUpload::make('image_path')
                            ->label(__('filament.product_image'))
                            ->disk('do_spaces')
                            ->directory('products')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                '1:1',
                                '4:3',
                                '16:9',
                            ])
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(4096)
                            ->getUploadedFileNameForStorageUsing(
                                fn(TemporaryUploadedFile $file): string => Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                                . '-'
                                . Str::random(8)
                                . '.'
                                . $file->getClientOriginalExtension()
                            )
                            ->downloadable()
                            ->openable()
                            ->previewable()
                            ->helperText(__('filament.recommended_square_or_4_3_image_max_4mb')),
                    ]),

                Section::make(__('filament.product_availability'))
                    ->description(__('filament.product_availability_desc'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DateTimePicker::make('available_from')
                                    ->label(__('filament.available_from'))
                                    ->seconds(false)
                                    ->nullable()
                                    ->helperText(__('filament.available_from_help')),

                                DateTimePicker::make('available_until')
                                    ->label(__('filament.available_until'))
                                    ->seconds(false)
                                    ->nullable()
                                    ->helperText(__('filament.available_until_help')),

                                TextInput::make('stock_quantity')
                                    ->label(__('filament.stock_quantity'))
                                    ->numeric()
                                    ->nullable()
                                    ->minValue(1)
                                    ->step(1)
                                    ->helperText(__('filament.stock_quantity_help')),

                                TextInput::make('daily_limit')
                                    ->label(__('filament.daily_limit'))
                                    ->integer()
                                    ->nullable()
                                    ->minValue(1)
                                    ->maxValue(4294967295)
                                    ->helperText(__('filament.daily_limit_help')),

                                TextInput::make('sold_quantity')
                                    ->label(__('filament.sold_quantity'))
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->step(1)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText(__('filament.sold_quantity_help')),

                                Select::make('min_vip_level')
                                    ->label(__('filament.min_vip_level'))
                                    ->options(fn() => VipLevel::query()->orderBy('level')->pluck('name', 'level'))
                                    ->placeholder(__('filament.no_vip_level_requirement'))
                                    ->nullable()
                                    ->rules(['integer', 'min:1'])
                                    ->exists('vip_levels', 'level')
                                    ->native(false)
                                    ->helperText(__('filament.min_vip_level_help')),
                            ]),
                    ]),

                Section::make(__('filament.pricing_and_income'))
                    ->description(__('filament.these_values_affect_product_purchase_daily_income_and_commission_calcu'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('price')
                                    ->label(__('filament.price'))
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->step('0.01'),

                                TextInput::make('max_purchase_per_user')
                                    ->label(__('filament.max_purchase_per_user'))
                                    ->numeric()
                                    ->required()
                                    ->default(2)
                                    ->minValue(1)
                                    ->step(1)
                                    ->helperText(__('filament.max_purchase_per_user_help')),

                                TextInput::make('daily_income')
                                    ->label(__('filament.income_per_claim'))
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.01')
                                    ->live(),

                                TextInput::make('duration_days')
                                    ->label(__('filament.duration_days'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->step(1)
                                    ->live(),

                                TextInput::make('claim_interval_hours')
                                    ->label(__('filament.claim_interval_hours'))
                                    ->numeric()
                                    ->required()
                                    ->default(24)
                                    ->minValue(1)
                                    ->step(1)
                                    ->live()
                                    ->helperText(__('filament.claim_interval_hours_help')),

                                TextInput::make('total_claims')
                                    ->label(__('filament.total_claims'))
                                    ->numeric()
                                    ->nullable()
                                    ->minValue(1)
                                    ->step(1)
                                    ->live()
                                    ->helperText(__('filament.total_claims_help')),

                                TextInput::make('extra_points')
                                    ->label(__('filament.extra_points'))
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->step(1)
                                    ->helperText(__('filament.extra_points_help')),

                                Placeholder::make('total_income_preview')
                                    ->label(__('filament.total_income_preview'))
                                    ->content(function (callable $get): string {
                                        $incomePerClaim = (float) ($get('daily_income') ?? 0);
                                        $durationDays = max(1, (int) ($get('duration_days') ?? 1));
                                        $claimIntervalHours = max(1, (int) ($get('claim_interval_hours') ?? 24));
                                        $manualTotalClaims = (int) ($get('total_claims') ?? 0);

                                        $effectiveClaims = $manualTotalClaims > 0
                                            ? $manualTotalClaims
                                            : max(1, (int) floor(($durationDays * 24) / $claimIntervalHours));

                                        $totalIncome = $incomePerClaim * $effectiveClaims;

                                        return config('app.currency') . ' ' . number_format($totalIncome, 2)
                                            . ' / '
                                            . number_format($effectiveClaims)
                                            . ' '
                                            . __('filament.claims');
                                    }),

                                TextInput::make('upgrade_bonus')
                                    ->label(__('filament.upgrade_bonus'))
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->step('0.01'),

                                TextInput::make('invite_bonus')
                                    ->label(__('filament.invite_bonus'))
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->step('0.01'),
                            ]),
                    ]),

                Section::make(__('filament.extra_metadata'))
                    ->collapsed()
                    ->schema([
                        KeyValue::make('meta')
                            ->label(__('filament.meta'))
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->nullable(),
                    ]),
            ]);
    }
}
