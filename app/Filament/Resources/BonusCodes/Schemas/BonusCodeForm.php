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
                Section::make(__('filament.bonus_code'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('owner_user_ids')
                                    ->label(__('filament.distribute_to_users'))
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
                                    ->helperText(__('filament.select_one_or_more_users_the_system_will_generate_one_bonus_code_for_e')),

                                Select::make('owner_user_id')
                                    ->label(__('filament.owner_user'))
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
                                    ->label(__('filament.type'))
                                    ->required()
                                    ->default(BonusCode::TYPE_ADMIN)
                                    ->options([
                                        BonusCode::TYPE_ADMIN => __('filament.admin'),
                                        BonusCode::TYPE_INVITE => __('filament.invite'),
                                        BonusCode::TYPE_UPGRADE => __('filament.upgrade'),
                                        BonusCode::TYPE_COMPENSATION => __('filament.compensation'),
                                    ])
                                    ->native(false)
                                    ->disabled(fn (?BonusCode $record): bool => $record?->isUsed() ?? false),

                                TextInput::make('code')
                                    ->label(__('filament.code'))
                                    ->maxLength(64)
                                    ->unique(ignoreRecord: true)
                                    ->placeholder(__('filament.leave_empty_to_auto_generate'))
                                    ->helperText(__('filament.for_multiple_users_leave_this_empty_each_user_needs_a_unique_code'))
                                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper(trim($state)) : null)
                                    ->disabled(fn (?BonusCode $record): bool => $record?->isUsed() ?? false),

                                TextInput::make('amount')
                                    ->label(__('filament.amount'))
                                    ->prefix(config('app.currency'))
                                    ->required()
                                    ->numeric()
                                    ->minValue(0.01)
                                    ->step('0.01')
                                    ->disabled(fn (?BonusCode $record): bool => $record?->isUsed() ?? false),

                                Select::make('status')
                                    ->label(__('filament.status'))
                                    ->required()
                                    ->default(BonusCode::STATUS_UNUSED)
                                    ->options([
                                        BonusCode::STATUS_UNUSED => __('filament.unused'),
                                        BonusCode::STATUS_USED => __('filament.used'),
                                        BonusCode::STATUS_CANCELLED => __('filament.cancelled'),
                                        BonusCode::STATUS_EXPIRED => __('filament.expired'),
                                    ])
                                    ->native(false)
                                    ->disabled(fn (?BonusCode $record): bool => $record?->isUsed() ?? false),

                                DateTimePicker::make('expires_at')
                                    ->label(__('filament.expires_at'))
                                    ->seconds(false)
                                    ->nullable()
                                    ->disabled(fn (?BonusCode $record): bool => $record?->isUsed() ?? false),

                                Textarea::make('remark')
                                    ->label(__('filament.remark_reason'))
                                    ->rows(3)
                                    ->columnSpanFull()
                                    ->placeholder(__('filament.example_manual_reward_invite_bonus_upgrade_bonus_compensation')),
                            ]),
                    ])->columnSpanFull(),

                // Section::make(__('filament.meta'))
                //     ->collapsed()
                //     ->schema([
                //         KeyValue::make('meta')
                //             ->label(__('filament.meta'))
                //             ->nullable(),
                //     ]),
            ]);
    }
}