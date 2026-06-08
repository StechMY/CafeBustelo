<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AdminTopUpsRelationManager extends RelationManager
{
    protected static string $relationship = 'adminTopUps';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.admin_top_ups');
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('adminUser.username')
                    ->label(__('filament.admin'))
                    ->placeholder('-'),

                TextColumn::make('wallet_type')
                    ->label(__('filament.wallet_type'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'recharge' => 'info',
                        'withdrawable' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('amount')
                    ->label(__('filament.amount'))
                    ->prefix(config('app.currency')." ")
                    ->sortable(),

                IconColumn::make('counts_as_recharge')
                    ->label(__('filament.counts_recharge'))
                    ->boolean(),

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
