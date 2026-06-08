<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EarnedCommissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'earnedCommissions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.earned_commissions');
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sourceUser.username')
                    ->label(__('filament.source_user'))
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('product.name')
                    ->label(__('filament.product'))
                    ->placeholder('-'),

                TextColumn::make('generation_level')
                    ->label(__('filament.level'))
                    ->formatStateUsing(fn ($state): string => match ((int) $state) {
                        1 => 'B',
                        2 => 'C',
                        3 => 'D',
                        default => (string) $state,
                    })
                    ->badge(),

                TextColumn::make('source_daily_income')
                    ->label(__('filament.source_daily_income'))
                    ->prefix(config('app.currency')." "),

                TextColumn::make('percentage')
                    ->label(__('filament.percentage'))
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
