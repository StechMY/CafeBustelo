<?php

namespace App\Filament\Resources\UserProducts\Schemas;

use App\Filament\Resources\GoldenEggDraws\GoldenEggDrawResource;
use App\Models\Product;
use App\Models\UserProduct;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.customer_information'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('user_id')
                                    ->label(__('filament.user_id')),

                                TextEntry::make('user.username')
                                    ->label(__('filament.username'))
                                    ->copyable()
                                    ->weight('bold'),

                                TextEntry::make('user.name')
                                    ->label(__('filament.name'))
                                    ->placeholder('-'),

                                TextEntry::make('user.phone')
                                    ->label(__('filament.phone'))
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('user.referral_code')
                                    ->label(__('filament.referral_code'))
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('user.referrer.username')
                                    ->label(__('filament.referred_by'))
                                    ->placeholder('-'),

                                TextEntry::make('user.status')
                                    ->label(__('filament.customer_status'))
                                    ->formatStateUsing(fn (string $state): string => __('filament.'.$state))
                                    ->badge()
                                    ->color(fn (?string $state): string => $state === 'active' ? 'success' : 'gray'),
                            ]),
                    ]),

                Section::make(__('filament.product_information'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('product_name')
                                    ->label(__('filament.product_name'))
                                    ->state(fn (UserProduct $record): string => $record->product?->name
                                        ?? data_get($record->meta, 'product_name_snapshot', '-'))
                                    ->weight('bold'),

                                TextEntry::make('product_code')
                                    ->label(__('filament.product_code'))
                                    ->state(fn (UserProduct $record): string => $record->product?->code
                                        ?? data_get($record->meta, 'product_code_snapshot', '-'))
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('product_id')
                                    ->label(__('filament.product_id')),

                                TextEntry::make('product_type_snapshot')
                                    ->label(__('filament.product_type'))
                                    ->formatStateUsing(fn (?string $state): string => Product::typeLabel($state) ?: '-')
                                    ->badge()
                                    ->color('info'),

                                TextEntry::make('duration_days_snapshot')
                                    ->label(__('filament.duration_days')),

                                TextEntry::make('claim_interval')
                                    ->label(__('filament.claim_interval'))
                                    ->state(fn (UserProduct $record): string => $record->effectiveClaimIntervalHours().' '.__('filament.hours')),

                                TextEntry::make('total_claims')
                                    ->label(__('filament.total_claims'))
                                    ->state(fn (UserProduct $record): string => (string) $record->effectiveTotalClaims()),
                            ]),
                    ]),

                Section::make(__('filament.purchase_information'))
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('original_price')
                                    ->label(__('golden_egg.original_price'))
                                    ->state(fn (UserProduct $record) => data_get($record->meta, 'original_price')
                                        ?? ($record->purchaseCoupon || data_get($record->meta, 'coupon_id') ? null : $record->price_snapshot))
                                    ->numeric(2)
                                    ->prefix(config('app.currency').' ')
                                    ->placeholder(__('golden_egg.not_recorded')),

                                TextEntry::make('used_coupon')
                                    ->label(__('golden_egg.purchase_coupon'))
                                    ->state(fn (UserProduct $record) => $record->purchaseCoupon?->id ?? data_get($record->meta, 'coupon_id'))
                                    ->formatStateUsing(fn ($state, UserProduct $record): string => '#'.$state
                                        .($record->purchaseCoupon?->draw ? ' · '.$record->purchaseCoupon->draw->prize_name : ''))
                                    ->url(fn (UserProduct $record): ?string => ($draw = $record->purchaseCoupon?->draw)
                                        && GoldenEggDrawResource::canAccess() && GoldenEggDrawResource::canView($draw)
                                            ? GoldenEggDrawResource::getUrl('view', ['record' => $draw]) : null)
                                    ->placeholder(__('golden_egg.no_coupon')),

                                TextEntry::make('discount_percent')
                                    ->label(__('golden_egg.discount_percent'))
                                    ->state(fn (UserProduct $record) => data_get($record->meta, 'discount_percent')
                                        ?? $record->purchaseCoupon?->discount_percent ?? 0)
                                    ->suffix('%'),

                                TextEntry::make('discount_amount')
                                    ->label(__('golden_egg.discount_amount'))
                                    ->state(fn (UserProduct $record) => data_get($record->meta, 'discount_amount',
                                        $record->purchaseCoupon || data_get($record->meta, 'coupon_id') ? null : 0))
                                    ->numeric(2)
                                    ->prefix(config('app.currency').' ')
                                    ->placeholder(__('golden_egg.not_recorded')),

                                TextEntry::make('price_snapshot')
                                    ->label(__('golden_egg.paid_amount'))
                                    ->numeric(2)
                                    ->weight('bold')
                                    ->prefix(config('app.currency').' '),

                                TextEntry::make('daily_income_snapshot')
                                    ->label(__('filament.income_per_claim'))
                                    ->prefix(config('app.currency').' '),

                                TextEntry::make('extra_points_snapshot')
                                    ->label(__('filament.extra_points'))
                                    ->placeholder('-'),

                                TextEntry::make('purchased_at')
                                    ->label(__('filament.purchased_at'))
                                    ->dateTime(),

                                TextEntry::make('payment_wallet_type')
                                    ->label(__('filament.payment_wallet_type'))
                                    ->state(fn (UserProduct $record): string => match (data_get($record->meta, 'payment_wallet_type')) {
                                        'recharge' => __('filament.recharge_wallet'),
                                        'withdrawable' => __('filament.withdrawable_wallet'),
                                        default => data_get($record->meta, 'payment_wallet_type', '-'),
                                    })
                                    ->badge(),
                            ]),
                    ]),

                Section::make(__('filament.claim_information'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('claimed_days')
                                    ->label(__('filament.claimed')),

                                TextEntry::make('missed_days')
                                    ->label(__('filament.missed_days')),

                                TextEntry::make('remaining_claims')
                                    ->label(__('filament.remaining_claims'))
                                    ->state(fn (UserProduct $record): string => (string) max(
                                        0,
                                        $record->effectiveTotalClaims() - $record->claimed_days,
                                    )),

                                TextEntry::make('claim_progress')
                                    ->label(__('filament.claim_progress'))
                                    ->state(fn (UserProduct $record): string => $record->claimed_days.' / '.$record->effectiveTotalClaims()),

                                TextEntry::make('next_claim_at')
                                    ->label(__('filament.next_claim_at'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('expires_at')
                                    ->label(__('filament.expires_at'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('status')
                                    ->label(__('filament.status'))
                                    ->formatStateUsing(fn (string $state): string => __('filament.user_product_status_'.$state))
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'expired' => 'gray',
                                        default => 'info',
                                    }),
                            ]),
                    ]),
            ]);
    }
}
