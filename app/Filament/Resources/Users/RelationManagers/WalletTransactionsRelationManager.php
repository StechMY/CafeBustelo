<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WalletTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'walletTransactions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.wallet_transactions');
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('wallet_type')
                    ->label(__('filament.wallet'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'recharge' => 'info',
                        'withdrawable' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('type')
                    ->label(__('filament.type'))
                    ->badge()
                    ->searchable(),

                TextColumn::make('direction')
                    ->label(__('filament.direction'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'credit' => 'success',
                        'debit' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('amount')
                    ->label(__('filament.amount'))
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                TextColumn::make('balance_before')
                    ->label(__('filament.before'))
                    ->prefix(config('app.currency')." "),

                TextColumn::make('balance_after')
                    ->label(__('filament.after'))
                    ->prefix(config('app.currency')." "),

                TextColumn::make('remarks')
                    ->label(__('filament.remarks'))
                    ->limit(50)
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label(__('filament.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
