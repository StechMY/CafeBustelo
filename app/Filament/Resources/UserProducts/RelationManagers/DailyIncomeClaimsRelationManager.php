<?php

namespace App\Filament\Resources\UserProducts\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DailyIncomeClaimsRelationManager extends RelationManager
{
    protected static string $relationship = 'dailyIncomeClaims';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.claim_history');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('claim_day_no')
                    ->label(__('filament.claim_number'))
                    ->sortable(),

                TextColumn::make('amount')
                    ->label(__('filament.amount'))
                    ->prefix(config('app.currency').' ')
                    ->sortable(),

                TextColumn::make('claim_date')
                    ->label(__('filament.claim_date'))
                    ->date()
                    ->sortable(),

                TextColumn::make('claimable_at')
                    ->label(__('filament.claimable_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('claimed_at')
                    ->label(__('filament.claimed_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('claim_day_no', 'desc');
    }
}
