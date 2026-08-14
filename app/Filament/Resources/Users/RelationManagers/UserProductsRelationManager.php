<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\UserProducts\UserProductResource;
use App\Models\UserProduct;
use App\Support\FilamentSupportScope;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('filament.purchased_products');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return parent::canViewForRecord($ownerRecord, $pageClass)
            && FilamentSupportScope::canAccessUser($ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->label(__('filament.product_name'))
                    ->state(fn (UserProduct $record): string => $record->product?->name
                        ?? data_get($record->meta, 'product_name_snapshot', '-'))
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('product.code')
                    ->label(__('filament.product_code'))
                    ->state(fn (UserProduct $record): string => $record->product?->code
                        ?? data_get($record->meta, 'product_code_snapshot', '-'))
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('price_snapshot')
                    ->label(__('filament.purchase_price'))
                    ->prefix(config('app.currency').' ')
                    ->sortable(),

                TextColumn::make('claim_progress')
                    ->label(__('filament.claim_progress'))
                    ->state(fn (UserProduct $record): string => $record->claimed_days.' / '.$record->effectiveTotalClaims()),

                TextColumn::make('missed_days')
                    ->label(__('filament.missed_days'))
                    ->sortable(),

                TextColumn::make('purchased_at')
                    ->label(__('filament.purchased_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('next_claim_at')
                    ->label(__('filament.next_claim_at'))
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label(__('filament.expires_at'))
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->formatStateUsing(fn (string $state): string => __('filament.user_product_status_'.$state))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'gray',
                        default => 'info',
                    }),
            ])
            ->defaultSort('purchased_at', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->url(fn (UserProduct $record): string => UserProductResource::getUrl('view', [
                        'record' => $record,
                    ])),
            ]);
    }
}
