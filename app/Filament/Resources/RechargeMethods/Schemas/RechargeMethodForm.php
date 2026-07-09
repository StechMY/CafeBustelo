<?php

namespace App\Filament\Resources\RechargeMethods\Schemas;

use App\Models\RechargeMethod;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RechargeMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.recharge_method_information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament.name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder(__('filament.recharge_method_name_placeholder')),

                                Select::make('status')
                                    ->label(__('filament.status'))
                                    ->options(RechargeMethod::statusOptions())
                                    ->default(RechargeMethod::STATUS_ACTIVE)
                                    ->required()
                                    ->native(false),

                                TextInput::make('bank_name')
                                    ->label(__('filament.bank_name'))
                                    ->maxLength(100)
                                    ->placeholder('Vietcombank'),

                                TextInput::make('account_name')
                                    ->label(__('filament.account_name'))
                                    ->maxLength(150)
                                    ->placeholder('COMPANY NAME'),

                                TextInput::make('account_no')
                                    ->label(__('filament.account_no'))
                                    ->maxLength(100)
                                    ->placeholder('123456789'),

                                Hidden::make('sort_order')
                                    ->label(__('filament.sort_order'))
                                    ->default(0),

                                FileUpload::make('qr_image_path')
                                    ->label(__('filament.qr_image'))
                                    ->image()
                                    ->disk('do_spaces')
                                    ->directory('recharge-method-qr')
                                    ->visibility('public')
                                    ->maxSize(5120)
                                    ->acceptedFileTypes([
                                        'image/jpeg',
                                        'image/png',
                                        'image/webp',
                                    ])
                                    ->imagePreviewHeight('220')
                                    ->columnSpanFull()
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('qr_image_disk', 'do_spaces')),

                                TextInput::make('qr_image_disk')
                                    ->default('do_spaces')
                                    ->hidden(),

                                Textarea::make('instructions')
                                    ->label(__('filament.instructions'))
                                    ->rows(3)
                                    ->placeholder(__('filament.recharge_method_instructions_placeholder'))
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}