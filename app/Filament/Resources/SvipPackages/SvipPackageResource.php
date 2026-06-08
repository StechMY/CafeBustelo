<?php

namespace App\Filament\Resources\SvipPackages;

use App\Filament\Resources\SvipPackages\Pages\CreateSvipPackage;
use App\Filament\Resources\SvipPackages\Pages\EditSvipPackage;
use App\Filament\Resources\SvipPackages\Pages\ListSvipPackages;
use App\Models\SvipPackage;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SvipPackageResource extends Resource
{
    protected static ?string $model = SvipPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?int $navigationSort = 80;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.platform_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament.svip_packages');
    }

    public static function getModelLabel(): string
    {
        return __('filament.svip_package');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament.svip_packages');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.svip_package'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('filament.name'))
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('price')
                                    ->label(__('filament.price'))
                                    ->required()
                                    ->numeric()
                                    ->prefix(config('app.currency', 'RM'))
                                    ->minValue(0),

                                TextInput::make('duration_days')
                                    ->label(__('filament.duration_days'))
                                    ->numeric()
                                    ->helperText(__('filament.leave_empty_for_lifetime')),

                                TextInput::make('sort_order')
                                    ->label(__('filament.sort_order'))
                                    ->numeric()
                                    ->default(0),

                                Toggle::make('is_active')
                                    ->label(__('filament.is_active'))
                                    ->default(true),

                                Textarea::make('description')
                                    ->label(__('filament.description'))
                                    ->columnSpanFull()
                                    ->rows(4),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament.name'))
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('price')
                    ->label(__('filament.price'))
                    ->money('MYR')
                    ->sortable(),

                TextColumn::make('duration_days')
                    ->label(__('filament.duration_days'))
                    ->formatStateUsing(fn ($state): string => $state ? __('filament.days_count', ['count' => $state]) : __('filament.lifetime'))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('filament.is_active'))
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label(__('filament.sort_order'))
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('filament.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSvipPackages::route('/'),
            'create' => CreateSvipPackage::route('/create'),
            'edit' => EditSvipPackage::route('/{record}/edit'),
        ];
    }
}
