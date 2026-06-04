<?php

namespace App\Filament\Resources\BonusCodes\Schemas;

use App\Models\BonusCode;
use App\Models\User;
use App\Services\BonusCodeService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BonusCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bonus Code')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('owner_user_ids')
                                    ->label('Distribute To Users')
                                    ->multiple()
                                    ->options(function (): array {
                                        return User::query()
                                            ->orderBy('username')
                                            ->limit(200)
                                            ->get()
                                            ->mapWithKeys(function (User $user): array {
                                                return [
                                                    $user->id => "{$user->username} - {$user->name}",
                                                ];
                                            })
                                            ->all();
                                    })
                                    ->searchable()
                                    ->required()
                                    ->dehydrated(false)
                                    ->visible(fn (string $operation): bool => $operation === 'create')
                                    ->helperText('Select one or more users. The system will generate one bonus code for each user.'),

                                Select::make('owner_user_id')
                                    ->label('Owner User')
                                    ->relationship(
                                        name: 'owner',
                                        titleAttribute: 'username',
                                        modifyQueryUsing: fn ($query) => $query->orderBy('username'),
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (User $record): string => "{$record->username} - {$record->name}")
                                    ->searchable(['username', 'name', 'phone', 'referral_code'])
                                    ->preload()
                                    ->required()
                                    ->visible(fn (string $operation): bool => $operation === 'edit')
                                    ->disabled(fn (?BonusCode $record): bool => $record?->isUsed() ?? false),

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
                                    ->native(false)
                                    ->disabled(fn (?BonusCode $record): bool => $record?->isUsed() ?? false),

                                TextInput::make('code')
                                    ->label('Code')
                                    ->maxLength(64)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('Leave empty to auto-generate')
                                    ->helperText('For multiple users, leave this empty. Each user needs a unique code.')
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(trim($state)) : null)
                                    ->disabled(fn (?BonusCode $record): bool => $record?->isUsed() ?? false),

                                TextInput::make('amount')
                                    ->label('Amount')
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->step('0.01')
                                    ->disabled(fn (?BonusCode $record): bool => $record?->isUsed() ?? false),

                                Select::make('status')
                                    ->label('Status')
                                    ->required()
                                    ->default(BonusCode::STATUS_UNUSED)
                                    ->options([
                                        BonusCode::STATUS_UNUSED => 'Unused',
                                        BonusCode::STATUS_USED => 'Used',
                                        BonusCode::STATUS_CANCELLED => 'Cancelled',
                                        BonusCode::STATUS_EXPIRED => 'Expired',
                                    ])
                                    ->native(false)
                                    ->disabled(fn (?BonusCode $record): bool => $record?->isUsed() ?? false),

                                DateTimePicker::make('expires_at')
                                    ->label('Expires At')
                                    ->seconds(false)
                                    ->nullable()
                                    ->disabled(fn (?BonusCode $record): bool => $record?->isUsed() ?? false),

                                Textarea::make('remark')
                                    ->label('Remark / Reason')
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->placeholder('Example: Manual reward / invite bonus / upgrade bonus / compensation.'),
                            ]),
                    ])->columnSpanFull(),

                // Section::make('Meta')
                //     ->collapsed()
                //     ->schema([
                //         KeyValue::make('meta')
                //             ->label('Meta')
                //             ->nullable(),
                //     ]),
            ]);
    }
}