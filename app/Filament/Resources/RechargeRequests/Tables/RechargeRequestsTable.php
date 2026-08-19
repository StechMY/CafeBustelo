<?php

namespace App\Filament\Resources\RechargeRequests\Tables;

use App\Models\RechargeRequest;
use App\Services\RechargeApprovalService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Throwable;

class RechargeRequestsTable
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
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('amount')
                    ->label(__('filament.amount'))
                    ->prefix(config('app.currency') . " ")
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('filament.status'))
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('reference_no')
                    ->label(__('filament.reference_no'))
                    ->searchable()
                    ->copyable()
                    ->placeholder('-'),

                TextColumn::make('payment_method_name')
                    ->label(__('filament.recharge_method'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('payment_bank_name')
                    ->label(__('filament.bank_name'))
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('payment_account_name')
                    ->label(__('filament.account_name'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_account_no')
                    ->label(__('filament.account_no'))
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),

                ImageColumn::make('receipt_path')->disk('do_spaces')->url(fn($record): ?string => $record?->receipt_url)
                    ->label(__('filament.receipt_path'))
                    ->openUrlInNewTab(),

                TextColumn::make('created_at')
                    ->label(__('filament.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),

                TextColumn::make('reviewer.username')
                    ->label(__('filament.reviewer'))
                    ->placeholder('-'),

                TextColumn::make('reviewed_at')
                    ->label(__('filament.reviewed_at'))
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => __('filament.pending'),
                        'approved' => __('filament.approved'),
                        'rejected' => __('filament.rejected'),
                    ]),
                Filter::make('created_at')
                    ->label(__('filament.created_at'))
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Created from'),

                        DatePicker::make('created_until')
                            ->label('Created until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date) =>
                                $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date) =>
                                $query->whereDate('created_at', '<=', $date)
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                // EditAction::make(),
                DeleteAction::make(),

                Action::make('approve')
                    ->label(__('filament.approve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(RechargeRequest $record): bool => $record->status === 'pending')
                    ->authorize(fn(RechargeRequest $record): bool => Gate::allows('audit', $record))
                    ->form([
                        Textarea::make('admin_remark')
                            ->label(__('filament.admin_remark'))
                            ->rows(3),
                    ])
                    ->requiresConfirmation()
                    ->action(function (RechargeRequest $record, array $data): void {
                        abort_unless(Gate::allows('audit', $record), 403);
                        try {
                            app(RechargeApprovalService::class)->approve(
                                rechargeRequest: $record,
                                adminUser: Filament::auth()->user(),
                                adminRemark: $data['admin_remark'] ?? null,
                            );

                            Notification::make()
                                ->title(__('filament.recharge_approved'))
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
                    ->visible(fn(RechargeRequest $record): bool => $record->status === 'pending')
                    ->form([
                        Textarea::make('admin_remark')
                            ->label(__('filament.reject_reason'))
                            ->required()
                            ->rows(3),
                    ])
                    ->authorize(fn(RechargeRequest $record): bool => Gate::allows('audit', $record))
                    ->requiresConfirmation()
                    ->action(function (RechargeRequest $record, array $data): void {
                        abort_unless(Gate::allows('audit', $record), 403);
                        try {
                            app(RechargeApprovalService::class)->reject(
                                rechargeRequest: $record,
                                adminUser: Filament::auth()->user(),
                                adminRemark: $data['admin_remark'] ?? null,
                            );

                            Notification::make()
                                ->title(__('filament.recharge_rejected'))
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
}