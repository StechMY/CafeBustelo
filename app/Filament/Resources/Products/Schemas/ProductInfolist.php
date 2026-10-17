<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.product'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                ImageEntry::make('image_path')
                                    ->label(__('filament.image'))
                                    ->disk('do_spaces')
                                    ->height(180)
                                    ->columnSpan(1),

                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label(__('filament.name'))
                                            ->weight('bold'),

                                        TextEntry::make('code')
                                            ->label(__('filament.code'))
                                            ->badge()
                                            ->color('info'),

                                        TextEntry::make('status')
                                            ->label(__('filament.status'))
                                            ->badge()
                                            ->color(fn(?string $state): string => match ($state) {
                                                'active' => 'success',
                                                'inactive' => 'gray',
                                                default => 'warning',
                                            }),

                                        TextEntry::make('sort_order')
                                            ->label(__('filament.sort_order'))
                                            ->numeric(),
                                    ])
                                    ->columnSpan(2),
                            ]),
                    ]),

                Section::make(__('filament.pricing_and_income'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('price')
                                    ->label(__('filament.price'))
                                    ->prefix(config('app.currency') . " "),
                                TextEntry::make('max_purchase_per_user')
                                    ->label(__('filament.max_purchase_per_user')),

                                TextEntry::make('min_vip_level')
                                    ->label(__('filament.min_vip_level'))
                                    ->formatStateUsing(fn($state): string => 'LV' . $state . '+')
                                    ->placeholder(__('filament.no_vip_level_requirement')),

                                TextEntry::make('daily_limit')
                                    ->label(__('filament.daily_limit'))
                                    ->placeholder(__('filament.unlimited'))
                                    ->numeric(),

                                TextEntry::make('daily_income')
                                    ->label(__('filament.daily_income'))
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('duration_days')
                                    ->label(__('filament.duration'))
                                    ->suffix(fn(): string => ' ' . __('filament.days'))
                                    ->numeric(),

                                TextEntry::make('total_income')
                                    ->label(__('filament.total_income'))
                                    ->state(fn(Product $record): float => (float) $record->daily_income * (int) $record->duration_days)
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('upgrade_bonus')
                                    ->label(__('filament.upgrade_bonus'))
                                    ->prefix(config('app.currency') . " "),

                                TextEntry::make('invite_bonus')
                                    ->label(__('filament.invite_bonus'))
                                    ->prefix(config('app.currency') . " "),

                                IconEntry::make('allow_coupons')
                                    ->label(__('golden_egg.allow_product_coupons'))
                                    ->boolean(),
                            ]),
                    ]),

                Section::make(__('filament.meta'))
                    ->collapsed()
                    ->schema([
                        KeyValueEntry::make('meta')
                            ->label(__('filament.meta'))
                            ->placeholder('-'),
                    ]),

                Section::make(__('filament.timestamps'))
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('filament.created_at'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('updated_at')
                                    ->label(__('filament.updated_at'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('deleted_at')
                                    ->label(__('filament.deleted_at'))
                                    ->dateTime()
                                    ->placeholder('-')
                                    ->visible(fn(Product $record): bool => $record->trashed()),
                            ]),
                    ]),
            ]);
    }
}
