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
                Section::make('Request')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('user.username')
                                    ->label('Username')
                                    ->weight('bold')
                                    ->copyable(),

                                TextEntry::make('user.name')
                                    ->label('Name'),

                                TextEntry::make('user.phone')
                                    ->label('Phone')
                                    ->copyable()
                                    ->placeholder('-'),

                                TextEntry::make('amount')
                                    ->label('Amount')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('fee_amount')
                                    ->label('Fee')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('net_amount')
                                    ->label('Net Amount')
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('vip_level_snapshot')
                                    ->label('VIP Snapshot')
                                    ->formatStateUsing(fn($state): string => 'LV' . $state),

                                TextEntry::make('min_withdraw_amount_snapshot')
                                    ->label('Min Withdraw Snapshot')
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
                                    ->label('Submitted At')
                                    ->dateTime(),
                            ]),
                    ]),

                Section::make('Bank Detail')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('bank_name')
                                    ->label('Bank Name')
                                    ->copyable(),

                                TextEntry::make('bank_account_name')
                                    ->label('Account Name')
                                    ->copyable(),

                                TextEntry::make('bank_account_no')
                                    ->label('Account No')
                                    ->copyable(),
                            ]),
                    ]),

                Section::make('Review')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('reviewer.username')
                                    ->label('Reviewed By')
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