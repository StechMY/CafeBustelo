<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CheckInRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'checkInRecords';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.check_in_records');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('claim_date')
                    ->label(__('filament.claim_date'))
                    ->date()
                    ->sortable(),

                TextColumn::make('vip_level_snapshot')
                    ->label(__('filament.vip'))
                    ->formatStateUsing(fn ($state): string => __('filament.lv') . $state)
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label(__('filament.amount'))
                    ->money('MYR')
                    ->sortable(),

                TextColumn::make('claimed_at')
                    ->label(__('filament.claimed_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('ip_address')
                    ->label(__('filament.ip_address'))
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('filament.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('claimed_at', 'desc');
    }
}
