<?php

namespace App\Filament\Resources\BankAccountReviewRequests\Tables;

use App\Models\BankAccountReviewRequest;
use App\Services\BankAccountReviewService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Throwable;

class BankAccountReviewRequestsTable
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

                TextColumn::make('user.phone')
                    ->label(__('filament.phone'))
                    ->searchable(),

                TextColumn::make('bank_account_name')
                    ->label(__('bank_account.real_name'))
                    ->searchable(),

                TextColumn::make('bank_name')
                    ->label(__('bank_account.bank_name'))
                    ->searchable(),

                TextColumn::make('bank_account_no')
                    ->label(__('bank_account.bank_account_no'))
                    ->formatStateUsing(fn ($record): string => $record->maskedAccountNo())
                    ->searchable(),

                TextColumn::make('id_photo_paths')
                    ->label(__('bank_account.id_photos'))
                    ->state(fn ($record): int => count($record->id_photo_paths ?? []))
                    ->badge(),

                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('submitted_at')
                    ->label(__('bank_account.submitted_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => __('filament.pending'),
                        'approved' => __('filament.approved'),
                        'rejected' => __('filament.rejected'),
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),

                Action::make('approve')
                    ->label(__('filament.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (BankAccountReviewRequest $record): bool => $record->isPending())
                    ->authorize(fn (BankAccountReviewRequest $record): bool => Gate::allows('update', $record))
                    ->form([
                        Textarea::make('admin_remark')
                            ->label(__('filament.admin_remark'))
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (BankAccountReviewRequest $record, array $data): void {
                        abort_unless(Gate::allows('update', $record), 403);

                        try {
                            app(BankAccountReviewService::class)->approve(
                                $record,
                                Filament::auth()->user(),
                                $data['admin_remark'] ?? null,
                            );

                            Notification::make()
                                ->title(__('filament.bank_account_review_approved'))
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title(__('filament.approve_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('reject')
                    ->label(__('filament.reject'))
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (BankAccountReviewRequest $record): bool => $record->isPending())
                    ->authorize(fn (BankAccountReviewRequest $record): bool => Gate::allows('update', $record))
                    ->form([
                        Textarea::make('admin_remark')
                            ->label(__('filament.reject_reason'))
                            ->required()
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (BankAccountReviewRequest $record, array $data): void {
                        abort_unless(Gate::allows('update', $record), 403);

                        try {
                            app(BankAccountReviewService::class)->reject(
                                $record,
                                Filament::auth()->user(),
                                $data['admin_remark'],
                            );

                            Notification::make()
                                ->title(__('filament.bank_account_review_rejected'))
                                ->success()
                                ->send();
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title(__('filament.reject_failed'))
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
