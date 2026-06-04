<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CheckInRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'checkInRecords';

    protected static ?string $title = 'Check-in Records';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('claim_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('vip_level_snapshot')
                    ->label('VIP')
                    ->formatStateUsing(fn ($state): string => 'LV' . $state)
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('amount')
                    ->money('MYR')
                    ->sortable(),

                TextColumn::make('claimed_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('ip_address')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('claimed_at', 'desc');
    }
}