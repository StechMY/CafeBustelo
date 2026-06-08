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
                                    ->prefix(config('app.currency')." "),

                                TextEntry::make('status')
                                    ->badge()
                                    ->color(fn (?string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        default => 'gray',
                                    }),

                                TextEntry::make('created_at')
                                    ->dateTime()
                                    ->placeholder('-'),
                            ]),
                    ]),

                Section::make(__('filament.payment_detail'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('bank_name')
                                    ->placeholder('-'),

                                TextEntry::make('reference_no')
                                    ->copyable()
                                    ->placeholder('-'),
                            ]),
                    ]),

                Section::make(__('filament.receipt'))
                    ->schema([
                        ImageEntry::make('receipt_path')
                            ->label(__('filament.receipt_preview'))
                            ->disk(fn ($record): string => $record->receipt_disk ?: 'do_spaces')
                            ->height(300)
                            ->visible(fn ($record): bool => $record->receipt_path && ! str_ends_with(strtolower($record->receipt_path), '.pdf')),

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