<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LoginLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'loginLogs';

    protected static ?string $title = 'IP Login History';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ip_address')
                    ->label('IP Address')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->limit(80)
                    ->tooltip(fn ($record): ?string => $record->user_agent),

                TextColumn::make('login_at')
                    ->label('Login At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('login_at', 'desc');
    }
}