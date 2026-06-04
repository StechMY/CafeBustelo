<?php

namespace App\Filament\Resources\RechargeRequests\Schemas;

use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class RechargeRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Recharge Request')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('user_id')
                                    ->label('User')
                                    ->relationship(
                                        name: 'user',
                                        titleAttribute: 'username',
                                        modifyQueryUsing: fn ($query) => $query->orderBy('username'),
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->username} - {$record->name}")
                                    ->searchable(['username', 'name', 'phone', 'referral_code'])
                                    ->preload()
                                    ->required(),

                                TextInput::make('amount')
                                    ->label('Amount')
                                    ->prefix(config('app.currency'))
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->step('0.01'),

                                Select::make('status')
                                    ->label('Status')
                                    ->required()
                                    ->default('pending')
                                    ->options([
                                        'pending' => 'Pending',
                                        'approved' => 'Approved',
                                        'rejected' => 'Rejected',
                                    ])
                                    ->native(false),

                                TextInput::make('bank_name')
                                    ->label('Bank Name')
                                    ->maxLength(100),

                                TextInput::make('reference_no')
                                    ->label('Reference No')
                                    ->maxLength(100),
                            ]),
                    ]),

                Section::make('Receipt')
                    ->schema([
                        FileUpload::make('receipt_path')
                            ->label('Receipt')
                            ->disk('do_spaces')
                            ->directory('recharge-receipts/admin')
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                                'application/pdf',
                            ])
                            ->maxSize(5120)
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                                    . '-'
                                    . Str::random(8)
                                    . '.'
                                    . $file->getClientOriginalExtension()
                            )
                            ->downloadable()
                            ->openable()
                            ->previewable(),

                    ]),
            ]);
    }
}