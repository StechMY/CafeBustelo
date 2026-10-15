<?php

namespace App\Filament\Resources\GoldenEggPrizes;

use App\Filament\Resources\GoldenEggPrizes\Pages\ManageGoldenEggPrizes;
use App\Models\GoldenEggPrize;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class GoldenEggPrizeResource extends Resource
{
    protected static ?string $model = GoldenEggPrize::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?int $navigationSort = 25;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.platform_management');
    }

    public static function getModelLabel(): string
    {
        return __('golden_egg.prize');
    }

    public static function getPluralModelLabel(): string
    {
        return __('golden_egg.admin_title');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('golden_egg.prize_name'))->required()->maxLength(100),
            Select::make('type')->label(__('golden_egg.prize_type'))->options([
                'cash' => __('golden_egg.cash'), 'coupon' => __('golden_egg.coupon'),
            ])->required()->live(),
            TextInput::make('amount')->label(__('golden_egg.cash_amount'))->numeric()
                ->minValue(0.01)->maxValue(999999999999.99)->step(0.01)
                ->required(fn (Get $get): bool => $get('type') === 'cash')
                ->visible(fn (Get $get): bool => $get('type') === 'cash'),
            TextInput::make('discount_percent')->label(__('golden_egg.discount_percent'))->numeric()
                ->minValue(0.01)->maxValue(100)->step(0.01)->suffix('%')
                ->helperText(__('golden_egg.discount_help'))
                ->required(fn (Get $get): bool => $get('type') === 'coupon')
                ->visible(fn (Get $get): bool => $get('type') === 'coupon'),
            TextInput::make('weight')->label(__('golden_egg.weight'))->numeric()->integer()
                ->minValue(0)->maxValue(1000000)->required()->helperText(__('golden_egg.weight_help')),
            Toggle::make('unlimited_stock')->label(__('golden_egg.unlimited_stock'))->live()
                ->afterStateHydrated(fn (Toggle $component, ?GoldenEggPrize $record) => $component->state($record?->stock === null)),
            TextInput::make('stock')->label(__('golden_egg.stock'))->numeric()->integer()
                ->minValue(0)->maxValue(4294967295)
                ->required(fn (Get $get): bool => ! $get('unlimited_stock'))
                ->hidden(fn (Get $get): bool => (bool) $get('unlimited_stock')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('slot')->label(__('golden_egg.slot'))->sortable(),
            TextColumn::make('name')->label(__('golden_egg.prize_name')),
            TextColumn::make('type')->label(__('golden_egg.prize_type'))->badge()
                ->formatStateUsing(fn (string $state): string => __('golden_egg.'.$state)),
            TextColumn::make('amount')->label(__('golden_egg.cash_amount'))->numeric(2),
            TextColumn::make('discount_percent')->label(__('golden_egg.discount_percent'))->suffix('%'),
            TextColumn::make('weight')->label(__('golden_egg.weight')),
            TextColumn::make('stock')->label(__('golden_egg.stock'))->placeholder(__('golden_egg.unlimited_stock')),
        ])->defaultSort('slot')->paginated(false)->recordActions([
            EditAction::make()->mutateDataUsing(function (array $data): array {
                $data['stock'] = ($data['unlimited_stock'] ?? false) ? null : $data['stock'];
                $data['amount'] = $data['type'] === 'cash' ? $data['amount'] : 0;
                $data['discount_percent'] = $data['type'] === 'coupon' ? $data['discount_percent'] : 0;
                unset($data['unlimited_stock']);

                return $data;
            }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageGoldenEggPrizes::route('/')];
    }
}
