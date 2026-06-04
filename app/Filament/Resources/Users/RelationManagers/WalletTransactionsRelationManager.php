<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WalletTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'walletTransactions';

    protected static ?string $title = 'Wallet Transactions';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('wallet_type')
                    ->label('Wallet')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'recharge' => 'info',
                        'withdrawable' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->searchable(),

                TextColumn::make('direction')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'credit' => 'success',
                        'debit' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('amount')
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('balance_before')
                    ->label('Before')
                    ->prefix(config('app.currency')." "),

                TextColumn::make('balance_after')
                    ->label('After')
                    ->prefix(config('app.currency')." "),

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