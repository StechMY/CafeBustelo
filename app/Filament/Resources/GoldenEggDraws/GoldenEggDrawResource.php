<?php

namespace App\Filament\Resources\GoldenEggDraws;

use App\Filament\Resources\GoldenEggDraws\Pages\ListGoldenEggDraws;
use App\Filament\Resources\GoldenEggDraws\Pages\ViewGoldenEggDraw;
use App\Filament\Resources\UserProducts\UserProductResource;
use App\Models\GoldenEggDraw;
use App\Support\FilamentSupportScope;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class GoldenEggDrawResource extends Resource
{
    protected static ?string $model = GoldenEggDraw::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 26;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.platform_management');
    }

    public static function getModelLabel(): string
    {
        return __('golden_egg.draw_record');
    }

    public static function getPluralModelLabel(): string
    {
        return __('golden_egg.draw_records');
    }

    public static function getEloquentQuery(): Builder
    {
        return FilamentSupportScope::applyToUserOwnedQuery(
            parent::getEloquentQuery()->with(['user', 'coupon.purchase']),
            'user_id',
        );
    }

    public static function couponStatus(GoldenEggDraw $record): string
    {
        if ($record->prize_type === 'cash') return __('golden_egg.cash_credited');
        if (! $record->coupon) return __('golden_egg.coupon_missing');

        return $record->coupon->used_at ? __('filament.used') : __('filament.unused');
    }

    public static function purchaseUrl(GoldenEggDraw $record): ?string
    {
        $purchase = $record->coupon?->purchase;

        return $purchase && ! $purchase->trashed() && UserProductResource::canAccess() && UserProductResource::canView($purchase)
            ? UserProductResource::getUrl('view', ['record' => $purchase]) : null;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label(__('golden_egg.draw_id'))->sortable()->searchable(),
            TextColumn::make('user.username')->label(__('filament.username'))->searchable()->sortable()->weight('bold'),
            TextColumn::make('prize_name')->label(__('golden_egg.prize_name'))->searchable()->wrap(),
            TextColumn::make('prize_type')->label(__('golden_egg.prize_type'))->badge()
                ->formatStateUsing(fn (string $state): string => __('golden_egg.'.$state))
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('reward_value')->label(__('golden_egg.reward_value'))->weight('bold')
                ->state(fn (GoldenEggDraw $record): string => $record->prize_type === 'cash'
                    ? config('app.currency').' '.$record->amount
                    : __('golden_egg.percent_off', ['percent' => $record->discount_percent])),
            TextColumn::make('coupon_status')->label(__('golden_egg.coupon_status'))->badge()
                ->state(fn (GoldenEggDraw $record): string => self::couponStatus($record)),
            TextColumn::make('coupon.id')->label(__('golden_egg.coupon_id'))->prefix('#')->placeholder('-')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('coupon.user_product_id')->label(__('golden_egg.used_order'))->prefix('#')->placeholder('-')
                ->url(fn (GoldenEggDraw $record): ?string => self::purchaseUrl($record)),
            TextColumn::make('coupon.used_at')->label(__('filament.used_at'))->dateTime()->placeholder('-')
                ->toggleable(isToggledHiddenByDefault: true),
            IconColumn::make('is_virtual')->label(__('filament.virtual'))->boolean()->toggleable(),
            TextColumn::make('created_at')->label(__('golden_egg.drawn_at'))->dateTime('Y-m-d H:i:s')->sortable(),
        ])->defaultSort('id', 'desc')->filters([
            SelectFilter::make('prize_type')->label(__('golden_egg.prize_type'))->options([
                'cash' => __('golden_egg.cash'), 'coupon' => __('golden_egg.coupon'),
            ]),
            SelectFilter::make('coupon_status')->label(__('golden_egg.coupon_status'))->options([
                'used' => __('filament.used'), 'unused' => __('filament.unused'),
            ])->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                'used' => $query->where('prize_type', 'coupon')->whereHas('coupon', fn (Builder $coupon) => $coupon->whereNotNull('used_at')),
                'unused' => $query->where('prize_type', 'coupon')->whereHas('coupon', fn (Builder $coupon) => $coupon->whereNull('used_at')),
                default => $query,
            }),
            TernaryFilter::make('is_virtual')->label(__('filament.virtual')),
        ])->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('golden_egg.draw_record'))->columnSpanFull()->columns(3)->schema([
                TextEntry::make('id')->label(__('golden_egg.draw_id')),
                TextEntry::make('user.username')->label(__('filament.username'))->weight('bold'),
                IconEntry::make('is_virtual')->label(__('filament.virtual'))->boolean(),
                TextEntry::make('created_at')->label(__('golden_egg.drawn_at'))->dateTime(),
                TextEntry::make('egg_number')->label(__('golden_egg.chosen_egg')),
                TextEntry::make('prize_name')->label(__('golden_egg.prize_name')),
                TextEntry::make('prize_type')->label(__('golden_egg.prize_type'))->badge()
                    ->formatStateUsing(fn (string $state): string => __('golden_egg.'.$state)),
                TextEntry::make('amount')->label(__('golden_egg.cash_amount'))->prefix(config('app.currency').' ')
                    ->visible(fn (GoldenEggDraw $record): bool => $record->prize_type === 'cash'),
                TextEntry::make('discount_percent')->label(__('golden_egg.discount_percent'))->suffix('%')
                    ->visible(fn (GoldenEggDraw $record): bool => $record->prize_type === 'coupon'),
                TextEntry::make('coupon_status')->label(__('golden_egg.coupon_status'))->badge()
                    ->state(fn (GoldenEggDraw $record): string => self::couponStatus($record)),
            ]),
            Section::make(__('golden_egg.purchase_coupon'))->columnSpanFull()->columns(3)
                ->visible(fn (GoldenEggDraw $record): bool => $record->prize_type === 'coupon')->schema([
                    TextEntry::make('coupon.id')->label(__('golden_egg.coupon_id'))->prefix('#')->placeholder('-'),
                    TextEntry::make('coupon.used_at')->label(__('filament.used_at'))->dateTime()->placeholder('-'),
                    TextEntry::make('coupon.user_product_id')->label(__('golden_egg.used_order'))->prefix('#')
                        ->placeholder(fn (GoldenEggDraw $record): string => $record->coupon?->used_at ? __('golden_egg.not_recorded') : '-')
                        ->url(fn (GoldenEggDraw $record): ?string => self::purchaseUrl($record)),
                    TextEntry::make('deleted_order')->label(__('filament.status'))->state(__('filament.deleted'))->badge()
                        ->visible(fn (GoldenEggDraw $record): bool => $record->coupon?->purchase?->trashed() ?? false),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListGoldenEggDraws::route('/'), 'view' => ViewGoldenEggDraw::route('/{record}')];
    }
}
