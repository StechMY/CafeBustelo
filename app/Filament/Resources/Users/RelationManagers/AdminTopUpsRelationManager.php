<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminTopUpsRelationManager extends RelationManager
{
    protected static string $relationship = 'adminTopUps';

    protected static ?string $title = 'Admin Top-ups';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('adminUser.username')
                    ->label('Admin')
                    ->placeholder('-'),

                TextColumn::make('wallet_type')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'recharge' => 'info',
                        'withdrawable' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('amount')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                IconColumn::make('counts_as_recharge')
                    ->label('Counts Recharge')
                    ->boolean(),

                TextColumn::make('remarks')
                    ->limit(50)
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}