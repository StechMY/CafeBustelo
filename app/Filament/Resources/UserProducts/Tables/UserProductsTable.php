<?php

namespace App\Filament\Resources\UserProducts\Tables;

use App\Models\Product;
use App\Models\UserProduct;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.username')
                    ->label(__('filament.username'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('user.name')
                    ->label(__('filament.name'))
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('user.phone')
                    ->label(__('filament.phone'))
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('product.name')
                    ->label(__('filament.product_name'))
                    ->state(fn(UserProduct $record): string => $record->product?->name
                        ?? data_get($record->meta, 'product_name_snapshot', '-'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->formatStateUsing(fn(string $state): string => __('filament.user_product_status_' . $state))
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'gray',
                        default => 'info',
                    })
                    ->sortable(),

                TextColumn::make('product_type_snapshot')
                    ->label(__('filament.product_type'))
                    ->formatStateUsing(fn(?string $state): string => Product::typeLabel($state) ?: '-')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('price_snapshot')
                    ->label(__('filament.purchase_price'))
                    ->prefix(config('app.currency') . ' ')
                    ->sortable(),

                TextColumn::make('daily_income_snapshot')
                    ->label(__('filament.income_per_claim'))
                    ->prefix(config('app.currency') . ' ')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('purchased_at')
                    ->label(__('filament.purchased_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('claim_progress')
                    ->label(__('filament.claim_progress'))
                    ->state(fn(UserProduct $record): string => $record->claimed_days . ' / ' . $record->effectiveTotalClaims()),

                TextColumn::make('claimed_days')
                    ->label(__('filament.claimed'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('effective_total_claims')
                    ->label(__('filament.total_claims'))
                    ->state(fn(UserProduct $record): string => (string) $record->effectiveTotalClaims())
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('missed_days')
                    ->label(__('filament.missed_days'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('next_claim_at')
                    ->label(__('filament.next_claim_at'))
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('expires_at')
                    ->label(__('filament.expires_at'))
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('product.code')
                    ->label(__('filament.product_code'))
                    ->state(fn(UserProduct $record): string => $record->product?->code
                        ?? data_get($record->meta, 'product_code_snapshot', '-'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('-')
                    ->toggleable(),



                TextColumn::make('payment_wallet_type')
                    ->label(__('filament.payment_wallet_type'))
                    ->state(fn(UserProduct $record): string => match (data_get($record->meta, 'payment_wallet_type')) {
                        'recharge' => __('filament.recharge_wallet'),
                        'withdrawable' => __('filament.withdrawable_wallet'),
                        default => data_get($record->meta, 'payment_wallet_type', '-'),
                    })
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('purchased_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => __('filament.user_product_status_active'),
                        'expired' => __('filament.user_product_status_expired'),
                    ]),

                SelectFilter::make('product_type_snapshot')
                    ->label(__('filament.product_type'))
                    ->options(Product::typeOptions()),

                SelectFilter::make('product_id')
                    ->label(__('filament.product'))
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('purchase_date')
                    ->label(__('filament.purchase_date'))
                    ->form([
                        DatePicker::make('from')
                            ->label(__('filament.start_date')),
                        DatePicker::make('until')
                            ->label(__('filament.end_date')),
                    ])
                    ->query(fn(Builder $query, array $data): Builder => $query
                        ->when(
                            $data['from'] ?? null,
                            fn(Builder $query, string $date): Builder => $query->whereDate('user_products.purchased_at', '>=', $date),
                        )
                        ->when(
                            $data['until'] ?? null,
                            fn(Builder $query, string $date): Builder => $query->whereDate('user_products.purchased_at', '<=', $date),
                        )),

                Filter::make('has_missed_claims')
                    ->label(__('filament.has_missed_claims'))
                    ->query(fn(Builder $query): Builder => $query->where('missed_days', '>', 0)),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
