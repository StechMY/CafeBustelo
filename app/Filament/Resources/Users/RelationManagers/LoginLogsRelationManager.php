<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class LoginLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'loginLogs';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.ip_login_history');
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ip_address')
                    ->label(__('filament.ip_address'))
                    ->searchable()
                    ->copyable(),

                TextColumn::make('user_agent')
                    ->label(__('filament.user_agent'))
                    ->limit(80)
                    ->tooltip(fn ($record): ?string => $record->user_agent),

                TextColumn::make('login_at')
                    ->label(__('filament.login_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('login_at', 'desc');
    }
}
