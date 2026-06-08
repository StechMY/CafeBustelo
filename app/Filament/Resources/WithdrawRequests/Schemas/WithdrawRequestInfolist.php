<?php

namespace App\Filament\Resources\WithdrawRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WithdrawRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament.request'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('user.username')
                                    ->label(__('filament.username'))
                                    ->weight('bold')
                                    ->copyable(),

                                TextEntry::make('user.name')
                                    ->label(__('filament.name')),

                                TextEntry::make('user.phone')
                                    ->label(__('filament.phone'))
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('amount')
                                    ->label(__('filament.amount'))
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('fee_amount')
                                    ->label(__('filament.fee'))
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('net_amount')
                                    ->label(__('filament.net_amount'))
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('vip_level_snapshot')
                                    ->label(__('filament.vip_snapshot'))
                                    ->formatStateUsing(fn($state): string => __('filament.lv') . $state),

                                TextEntry::make('min_withdraw_amount_snapshot')
                                    ->label(__('filament.min_withdraw_snapshot'))
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn(?string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'gray',
                                    }),

                                TextEntry::make('created_at')
                                    ->label(__('filament.submitted_at'))
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make(__('filament.bank_detail'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('bank_name')
                                    ->label(__('filament.bank_name'))
                                    ->copyable(),

                                TextEntry::make('bank_account_name')
                                    ->label(__('filament.account_name'))
                                    ->copyable(),

                                TextEntry::make('bank_account_no')
                                    ->label(__('filament.account_no'))
                                    ->copyable(),
                            ]),
                    ]),

                Section::make(__('filament.review'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('reviewer.username')
                                    ->label(__('filament.reviewed_by'))
                                    ->placeholder('-'),

                                TextEntry::make('reviewed_at')
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('admin_remark')
                                    ->placeholder('-'),
                            ]),
                    ]),
            ]);
    }
}