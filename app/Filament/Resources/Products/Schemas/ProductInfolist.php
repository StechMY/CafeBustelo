<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
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
                Section::make('Product')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                ImageEntry::make('image_path')
                                    ->label('Image')
                                    ->disk('do_spaces')
                                    ->height(180)
                                    ->columnSpan(1),

                                Grid::make(2)
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Name')
                                            ->weight('bold'),

                                        TextEntry::make('code')
                                            ->label('Code')
                                            ->badge()
                                            ->color('info'),

                                        TextEntry::make('status')
                                            ->label('Status')
                                            ->badge()
                                            ->color(fn (?string $state): string => match ($state) {
                                                'active' => 'success',
                                                'inactive' => 'gray',
                                                default => 'warning',
                                            }),

                                        TextEntry::make('sort_order')
                                            ->label('Sort Order')
                                            ->numeric(),
                                    ])
                                    ->columnSpan(2),
                            ]),
                    ]),

                Section::make('Pricing & Income')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('price')
                                    ->label('Price')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('daily_income')
                                    ->label('Daily Income')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('duration_days')
                                    ->label('Duration')
                                    ->suffix(' days')
                                    ->numeric(),

                                TextEntry::make('total_income')
                                    ->label('Total Income')
                                    ->state(fn (Product $record): float => (float) $record->daily_income * (int) $record->duration_days)
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('upgrade_bonus')
                                    ->label('Upgrade Bonus')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('invite_bonus')
                                    ->label('Invite Bonus')
                                    ->prefix(config('app.currency')." "),
                            ]),
                    ]),

                Section::make('Meta')
                    ->collapsed()
                    ->schema([
                        KeyValueEntry::make('meta')
                            ->label('Meta')
                            ->placeholder('-'),
                    ]),

                Section::make('Timestamps')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created At')
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('updated_at')
                                    ->label('Updated At')
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('deleted_at')
                                    ->label('Deleted At')
                                    ->dateTime()
                                    ->placeholder('-')
                                    ->visible(fn (Product $record): bool => $record->trashed()),
                            ]),
                    ]),
            ]);
    }
}