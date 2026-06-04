<?php

namespace App\Filament\Resources\Products\Schemas;

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
                Section::make('Product Information')
                    ->description('Basic product package information.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Product Name')
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
                                    ->label('Product Code')
                                    ->required()
                                    ->maxLength(32)
                                    ->unique(ignoreRecord: true)
                                    ->alphaDash()
                                    ->dehydrateStateUsing(fn(?string $state): ?string => $state ? Str::upper($state) : null),

                                Select::make('status')
                                    ->label('Status')
                                    ->required()
                                    ->default('active')
                                    ->options([
                                        'active' => 'Active',
                                        'inactive' => 'Inactive',
                                    ])
                                    ->native(false),

                                TextInput::make('sort_order')
                                    ->label('Sort Order')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ]),
                    ]),

                Section::make('Product Image')
                    ->description('Uploaded to DigitalOcean Spaces.')
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Product Image')
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
                            ->helperText('Recommended: square or 4:3 image. Max 4MB.'),
                    ]),

                Section::make('Pricing & Income')
                    ->description('These values affect product purchase, daily income, and commission calculation.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('price')
                                    ->label('Price')
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->step('0.01'),

                                TextInput::make('daily_income')
                                    ->label('Daily Income')
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step('0.01')
                                    ->live(),

                                TextInput::make('duration_days')
                                    ->label('Duration Days')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->step(1)
                                    ->live(),

                                Placeholder::make('total_income_preview')
                                    ->label('Total Income Preview')
                                    ->content(function (callable $get): string {
                                        $dailyIncome = (float) ($get('daily_income') ?? 0);
                                        $durationDays = (int) ($get('duration_days') ?? 0);

                                        return config('app.currency') . " " . number_format($dailyIncome * $durationDays, 2);
                                    }),

                                TextInput::make('upgrade_bonus')
                                    ->label('Upgrade Bonus')
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->step('0.01'),

                                TextInput::make('invite_bonus')
                                    ->label('Invite Bonus')
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->step('0.01'),
                            ]),
                    ]),

                Section::make('Extra Metadata')
                    ->collapsed()
                    ->schema([
                        KeyValue::make('meta')
                            ->label('Meta')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->nullable(),
                    ]),
            ]);
    }
}