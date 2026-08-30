<?php

namespace App\Filament\Resources\RechargeRequests\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RechargeRequestInfolist
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
                                    ->prefix(config('app.currency').' '),

                                TextEntry::make('status')
                                    ->label(__('filament.status'))
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'gray',
                                    }),

                                TextEntry::make('created_at')
                                    ->label(__('filament.created_at'))
                                    ->dateTime()
                                    ->placeholder('-'),
                            ]),
                    ]),

                Section::make(__('filament.payment_detail'))
                    ->visible(fn ($record): bool => $record->isManualDeposit())
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // TextEntry::make('bank_name')
                                //     ->label(__('filament.bank_name'))
                                //     ->placeholder('-'),

                                TextEntry::make('reference_no')
                                    ->label(__('filament.reference_no'))
                                    ->copyable()
                                    ->placeholder('-'),
                            ]),
                    ]),

                Section::make(__('filament.gateway_details'))
                    ->visible(fn ($record): bool => $record->isGatewayDeposit())
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('gateway_provider')->label(__('filament.gateway_provider'))->placeholder('-'),
                                TextEntry::make('merchant_order_id')->label(__('filament.merchant_order_id'))->copyable()->placeholder('-'),
                                TextEntry::make('gateway_payment_status')->label(__('filament.gateway_status'))->badge()->placeholder('-'),
                                TextEntry::make('gateway_order_uid')->label(__('filament.gateway_order_uid'))->copyable()->placeholder('-'),
                                TextEntry::make('gateway_payment_uid')->label(__('filament.gateway_payment_uid'))->copyable()->placeholder('-'),
                                TextEntry::make('gateway_final_amount')->label(__('filament.gateway_final_amount'))->placeholder('-'),
                                TextEntry::make('gateway_currency')->label(__('filament.gateway_currency'))->placeholder('-'),
                                TextEntry::make('gateway_channel')->label(__('filament.gateway_channel'))->placeholder('-'),
                                TextEntry::make('gateway_fail_reason')
                                    ->label(__('filament.latest_gateway_error'))
                                    ->helperText(__('filament.latest_gateway_error_help'))
                                    ->placeholder('-'),
                                TextEntry::make('gateway_completed_at')->label(__('filament.gateway_completed_at'))->dateTime()->placeholder('-'),
                                TextEntry::make('gateway_last_callback_at')->label(__('filament.gateway_last_callback_at'))->dateTime()->placeholder('-'),
                                TextEntry::make('gateway_last_synced_at')->label(__('filament.gateway_last_synced_at'))->dateTime()->placeholder('-'),
                            ]),
                    ]),

                Section::make(__('filament.payment_information'))
                    ->visible(fn ($record): bool => $record->isManualDeposit())
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('payment_method_name')
                                    ->label(__('filament.recharge_method'))
                                    ->placeholder('-'),

                                TextEntry::make('payment_bank_name')
                                    ->label(__('filament.bank_name'))
                                    ->placeholder('-'),

                                TextEntry::make('payment_account_name')
                                    ->label(__('filament.account_name'))
                                    ->placeholder('-'),

                                TextEntry::make('payment_account_no')
                                    ->label(__('filament.account_no'))
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('reference_no')
                                    ->label(__('filament.reference_no'))
                                    ->copyable()
                                    ->placeholder('-'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make(__('filament.receipt'))
                    ->columnSpanFull()
                    ->schema([
                        ImageEntry::make('receipt_path')
                            ->label(__('filament.receipt_preview'))
                            ->disk(fn ($record): string => $record->receipt_disk ?: 'do_spaces')
                            ->height(360)
                            ->extraAttributes([
                                'class' => 'w-full overflow-hidden rounded-xl bg-gray-50',
                            ])
                            ->visible(fn ($record): bool => filled($record->receipt_path)
                                && ! str_ends_with(strtolower($record->receipt_path), '.pdf')),
                    ]),

                Section::make(__('filament.review'))
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('reviewer.username')
                                    ->label(__('filament.reviewed_by'))
                                    ->placeholder('-'),

                                TextEntry::make('reviewed_at')
                                    ->label(__('filament.reviewed_at'))
                                    ->dateTime()
                                    ->placeholder('-'),

                                TextEntry::make('admin_remark')
                                    ->label(__('filament.admin_remark'))
                                    ->placeholder('-'),
                            ]),
                    ]),
            ]);
    }
}
