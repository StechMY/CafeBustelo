<?php

namespace App\Filament\Resources\BankAccountReviewRequests\Schemas;

use App\Models\BankAccountReviewRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class BankAccountReviewRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('filament.request'))
                ->schema([
                    Grid::make(3)->schema([
                        TextEntry::make('user.username')
                            ->label(__('filament.username'))
                            ->weight('bold')
                            ->copyable(),
                        TextEntry::make('user.name')
                            ->label(__('filament.name')),
                        TextEntry::make('user.phone')
                            ->label(__('filament.phone'))
                            ->copyable(),
                        TextEntry::make('bank_account_name')
                            ->label(__('bank_account.real_name'))
                            ->copyable(),
                        TextEntry::make('bank_name')
                            ->label(__('bank_account.bank_name')),
                        TextEntry::make('bank_account_no')
                            ->label(__('bank_account.bank_account_no'))
                            ->copyable(),
                        TextEntry::make('phone')
                            ->label(__('bank_account.phone'))
                            ->copyable(),
                        TextEntry::make('status')
                            ->label(__('filament.status'))
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('submitted_at')
                            ->label(__('bank_account.submitted_at'))
                            ->dateTime(),
                    ]),
                ]),

            Section::make(__('bank_account.id_photos'))
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('id_photo_previews')
                        ->label('')
                        ->state('previews')
                        ->formatStateUsing(fn (BankAccountReviewRequest $record): HtmlString => new HtmlString(
                            '<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">'.
                            collect($record->id_photo_paths ?? [])->keys()->map(function (int $index) use ($record): string {
                                $url = e($record->idPhotoUrl($index));

                                return '<a href="'.$url.'" target="_blank" rel="noopener" class="block overflow-hidden rounded-xl border bg-gray-50">'
                                    .'<img src="'.$url.'" alt="ID photo '.($index + 1).'" class="h-64 w-full object-contain">'
                                    .'</a>';
                            })->implode('').
                            '</div>',
                        ))
                        ->html(),
                ]),

            Section::make(__('filament.review'))
                ->schema([
                    Grid::make(3)->schema([
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
