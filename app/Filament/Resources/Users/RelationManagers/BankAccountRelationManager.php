<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class BankAccountRelationManager extends RelationManager
{
    protected static string $relationship = 'bankAccount';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('bank_account.bound_bank_account');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('bank_account.bound_bank_account'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('bank_account_name')
                                    ->label(__('bank_account.real_name'))
                                    ->required()
                                    ->maxLength(150),

                                TextInput::make('bank_name')
                                    ->label(__('bank_account.bank_name'))
                                    ->required()
                                    ->maxLength(100),

                                TextInput::make('bank_account_no')
                                    ->label(__('bank_account.bank_account_no'))
                                    ->required()
                                    ->maxLength(100)
                                    ->rule(function (?Model $record) {
                                        return Rule::unique('user_bank_accounts', 'bank_account_no')
                                            ->ignore($record?->id);
                                    }),

                                Select::make('status')
                                    ->label(__('filament.status'))
                                    ->options([
                                        'active' => __('filament.active'),
                                        'inactive' => __('filament.inactive'),
                                        'suspended' => __('filament.suspended'),
                                    ])
                                    ->default('active')
                                    ->required()
                                    ->native(false),

                                Hidden::make('is_locked')
                                    ->default(true),

                                Hidden::make('bound_at')
                                    ->default(now()),
                            ]),

                        Textarea::make('meta.admin_note')
                            ->label(__('filament.admin_remark'))
                            ->rows(3)
                            ->helperText(__('bank_account.admin_change_note')),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('bank_account_name')
            ->columns([
                TextColumn::make('bank_account_name')
                    ->label(__('bank_account.real_name'))
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('bank_name')
                    ->label(__('bank_account.bank_name'))
                    ->searchable(),

                TextColumn::make('bank_account_no')
                    ->label(__('bank_account.bank_account_no'))
                    ->formatStateUsing(function (?string $state): string {
                        $number = preg_replace('/\D+/', '', (string) $state);

                        if ($number === '') {
                            return $state ?: '-';
                        }

                        return str_repeat('*', max(strlen($number) - 4, 0)) . substr($number, -4);
                    })
                    ->copyable(),

                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'suspended' => 'danger',
                        default => 'warning',
                    }),

                IconColumn::make('is_locked')
                    ->label(__('bank_account.locked'))
                    ->boolean(),

                TextColumn::make('bound_at')
                    ->label(__('bank_account.bound_at'))
                    ->dateTime('d M Y, h:i A'),

                TextColumn::make('updated_at')
                    ->label(__('filament.updated_at'))
                    ->dateTime('d M Y, h:i A')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('bank_account.bind_bank_account'))
                    ->visible(fn(): bool => !$this->getOwnerRecord()->bankAccount()->where('status','active')->exists())
                    ->mutateDataUsing(function (array $data): array {
                        $data['user_id'] = $this->getOwnerRecord()->id;
                        $data['is_locked'] = true;
                        $data['bound_at'] ??= now();

                        $data['meta']['source'] = 'admin_bind';
                        $data['meta']['admin_user_id'] = auth('admin')->id();

                        return $data;
                    }),
            ])
            ->actions([
                ViewAction::make(),

                EditAction::make()
                    ->label(__('filament.edit'))
                    ->mutateDataUsing(function (array $data): array {
                        $data['is_locked'] = true;

                        $data['meta']['last_changed_by_admin_user_id'] = auth('admin')->id();
                        $data['meta']['last_changed_at'] = now()->toDateTimeString();

                        return $data;
                    }),
            ])
            ->paginated(false);
    }
}