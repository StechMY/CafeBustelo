<?php

namespace App\Filament\Resources\RedPacketPrizes;

use App\Filament\Resources\RedPacketPrizes\Pages\ManageRedPacketPrizes;
use App\Models\RedPacketPrize;
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

class RedPacketPrizeResource extends Resource
{
    protected static ?string $model = RedPacketPrize::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?int $navigationSort = 27;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.platform_management');
    }

    public static function getModelLabel(): string
    {
        return __('red_packet.prize');
    }

    public static function getPluralModelLabel(): string
    {
        return __('red_packet.admin_title');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('red_packet.prize_name'))->required()->maxLength(100),
            Select::make('type')->label(__('red_packet.prize_type'))->options([
                'cash' => __('red_packet.cash'), 'coupon' => __('red_packet.coupon'), 'physical' => __('red_packet.physical'),
            ])->required()->live(),
            TextInput::make('amount')->label(__('red_packet.cash_amount'))->numeric()
                ->minValue(fn (Get $get): float => $get('type') === 'cash' ? 0.01 : 0)->maxValue(999999999999.99)->step(0.01)
                ->required(fn (Get $get): bool => $get('type') !== 'coupon')
                ->visible(fn (Get $get): bool => $get('type') !== 'coupon'),
            TextInput::make('discount_percent')->label(__('red_packet.discount_percent'))->numeric()
                ->minValue(0.01)->maxValue(100)->step(0.01)->suffix('%')
                ->helperText(__('red_packet.discount_help'))
                ->required(fn (Get $get): bool => $get('type') === 'coupon')
                ->visible(fn (Get $get): bool => $get('type') === 'coupon'),
            TextInput::make('weight')->label(__('red_packet.weight'))->numeric()->integer()
                ->minValue(0)->maxValue(1000000)->required()->helperText(__('red_packet.weight_help')),
            Toggle::make('unlimited_stock')->label(__('red_packet.unlimited_stock'))->live()
                ->afterStateHydrated(fn (Toggle $component, ?RedPacketPrize $record) => $component->state($record?->stock === null)),
            TextInput::make('stock')->label(__('red_packet.stock'))->numeric()->integer()
                ->minValue(0)->maxValue(4294967295)
                ->required(fn (Get $get): bool => ! $get('unlimited_stock'))
                ->hidden(fn (Get $get): bool => (bool) $get('unlimited_stock')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('slot')->label(__('red_packet.slot'))->sortable(),
            TextColumn::make('name')->label(__('red_packet.prize_name')),
            TextColumn::make('type')->label(__('red_packet.prize_type'))->badge()
                ->formatStateUsing(fn (string $state): string => __('red_packet.'.$state)),
            TextColumn::make('amount')->label(__('red_packet.cash_amount'))->numeric(2),
            TextColumn::make('discount_percent')->label(__('red_packet.discount_percent'))->suffix('%'),
            TextColumn::make('weight')->label(__('red_packet.weight')),
            TextColumn::make('stock')->label(__('red_packet.stock'))->placeholder(__('red_packet.unlimited_stock')),
        ])->defaultSort('slot')->paginated(false)->recordActions([
            EditAction::make()->mutateDataUsing(function (array $data): array {
                $data['stock'] = ($data['unlimited_stock'] ?? false) ? null : $data['stock'];
                $data['amount'] = $data['type'] !== 'coupon' ? $data['amount'] : 0;
                $data['discount_percent'] = $data['type'] === 'coupon' ? $data['discount_percent'] : 0;
                unset($data['unlimited_stock']);

                return $data;
            }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRedPacketPrizes::route('/')];
    }
}
