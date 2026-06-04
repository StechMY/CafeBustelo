<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EarnedCommissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'earnedCommissions';

    protected static ?string $title = 'Earned Commissions';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sourceUser.username')
                    ->label('Source User')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->placeholder('-'),

                TextColumn::make('generation_level')
                    ->label('Level')
                    ->formatStateUsing(fn ($state): string => match ((int) $state) {
                        1 => 'B',
                        2 => 'C',
                        3 => 'D',
                        default => (string) $state,
                    })
                    ->badge(),

                TextColumn::make('source_daily_income')
                    ->label('Source Daily Income')
                    ->prefix(config('app.currency')." "),

                TextColumn::make('percentage')
                    ->label('Percentage')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2) . '%'),

                TextColumn::make('amount')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'paid' => 'success',
                        'void' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}