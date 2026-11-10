<?php

namespace App\Filament\Resources\RedPacketDraws;

use App\Filament\Resources\RedPacketDraws\Pages\ListRedPacketDraws;
use App\Models\RedPacketDraw;
use App\Support\FilamentSupportScope;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RedPacketDrawResource extends Resource
{
    protected static ?string $model = RedPacketDraw::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';
    protected static ?int $navigationSort = 28;

    public static function getNavigationGroup(): string|UnitEnum|null { return __('filament.platform_management'); }
    public static function getModelLabel(): string { return __('red_packet.draw_records'); }
    public static function getPluralModelLabel(): string { return __('red_packet.draw_records'); }

    public static function getEloquentQuery(): Builder
    {
        return FilamentSupportScope::applyToUserOwnedQuery(parent::getEloquentQuery()->with(['user', 'coupon']), 'user_id');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('#')->sortable(),
            TextColumn::make('user.username')->label(__('filament.username'))->searchable(),
            TextColumn::make('prize_name')->label(__('red_packet.prize_name'))->searchable()->wrap(),
            TextColumn::make('prize_type')->label(__('red_packet.prize_type'))->badge()->formatStateUsing(fn (string $state) => __('red_packet.'.$state)),
            TextColumn::make('amount')->label(__('red_packet.cash_amount'))->numeric(0)->suffix(' VND'),
            TextColumn::make('discount_percent')->label(__('red_packet.discount_percent'))->suffix('%'),
            TextColumn::make('coupon.used_at')->label(__('filament.used_at'))->dateTime()->placeholder('—'),
            TextColumn::make('coupon.user_product_id')->label(__('red_packet.used_order'))->placeholder('—'),
            IconColumn::make('is_virtual')->label(__('filament.virtual'))->boolean(),
            TextColumn::make('created_at')->label(__('red_packet.drawn_at'))->dateTime()->sortable(),
        ])->defaultSort('id', 'desc')->filters([
            SelectFilter::make('prize_type')->label(__('red_packet.prize_type'))->options([
                'cash' => __('red_packet.cash'), 'coupon' => __('red_packet.coupon'), 'physical' => __('red_packet.physical'),
            ]),
            TernaryFilter::make('is_virtual')->label(__('filament.virtual')),
        ]);
    }

    public static function getPages(): array { return ['index' => ListRedPacketDraws::route('/')]; }
}
