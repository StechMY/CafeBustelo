<?php

namespace App\Filament\Pages;

use App\Filament\Resources\RedPacketDraws\RedPacketDrawResource;
use App\Models\RedPacketEntry;
use App\Models\User;
use App\Support\FilamentSupportScope;
use Filament\Forms\Components\DateTimePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use UnitEnum;

class RedPacketIssuanceRecords extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.red-packet-issuance-records';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?int $navigationSort = 29;

    #[Locked]
    public ?User $recipient = null;

    public function mount(): void
    {
        if (! request()->has('recipient')) {
            return;
        }

        abort_unless(static::canAccess(), 403);
        $this->recipient = FilamentSupportScope::applyToUsersQuery(User::query())
            ->findOrFail(request()->integer('recipient'));
        $filters = request()->validate([
            'method' => 'sometimes|in:backfill,live,legacy,all',
            'from' => 'nullable|date',
            'until' => 'nullable|date',
        ]);
        $method = $filters['method'] ?? 'backfill';
        $this->tableFilters = [
            'issuance' => ['value' => $method === 'all' ? null : $method],
            'issued_at' => ['from' => $filters['from'] ?? null, 'until' => $filters['until'] ?? null],
        ];
    }

    public static function canAccess(): bool
    {
        return RedPacketDrawResource::canViewAny();
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.platform_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('red_packet.issuance_records');
    }

    public function getTitle(): string
    {
        return $this->recipient ? __('red_packet.issuance_details', ['username' => $this->recipient->username]) : static::getNavigationLabel();
    }

    public function getBreadcrumbs(): array
    {
        return $this->recipient
            ? [static::getUrl() => static::getNavigationLabel(), $this->recipient->username]
            : parent::getBreadcrumbs();
    }

    public function getSubheading(): string
    {
        return __($this->recipient ? 'red_packet.issuance_detail_help' : 'red_packet.issuance_help', ['timezone' => config('app.timezone')]);
    }

    public function table(Table $table): Table
    {
        $table = $this->recipient ? $this->detailTable($table) : $this->summaryTable($table);

        return $this->applyIssuanceFilters($table);
    }

    private function summaryTable(Table $table): Table
    {
        $query = User::query()->join('red_packet_entries as entries', 'entries.user_id', '=', 'users.id')
            ->select('users.id', 'users.username')
            ->selectRaw('COUNT(*) AS chances')
            ->selectRaw("SUM(CASE WHEN entries.source = 'invite' THEN 1 ELSE 0 END) AS invites")
            ->selectRaw("SUM(CASE WHEN entries.source = 'milestone_3' THEN 1 ELSE 0 END) AS bonus_three")
            ->selectRaw("SUM(CASE WHEN entries.source = 'milestone_5' THEN 1 ELSE 0 END) AS bonus_five")
            ->selectRaw("SUM(CASE WHEN entries.source = 'upgrade' THEN 1 ELSE 0 END) AS upgrades")
            ->groupBy('users.id', 'users.username');

        return $table->query(FilamentSupportScope::applyToUsersQuery($query))
            ->columns([
                TextColumn::make('username')->label(__('filament.username'))->searchable()->sortable()->color('primary')
                    ->url(fn (User $record): string => static::getUrl([
                        'recipient' => $record->id,
                        'method' => $this->getTableFilterState('issuance')['value'] ?? 'all',
                        'from' => $this->getTableFilterState('issued_at')['from'] ?? null,
                        'until' => $this->getTableFilterState('issued_at')['until'] ?? null,
                    ])),
                TextColumn::make('chances')->label(__('red_packet.issued_total'))->sortable(),
                TextColumn::make('invites')->label(__('red_packet.invite_first_purchase')),
                TextColumn::make('bonus_three')->label(__('red_packet.milestone_3_source')),
                TextColumn::make('bonus_five')->label(__('red_packet.milestone_5_source')),
                TextColumn::make('upgrades')->label(__('red_packet.upgrade')),
            ])->defaultSort('chances', 'desc');
    }

    private function detailTable(Table $table): Table
    {
        abort_unless(static::canAccess() && FilamentSupportScope::canAccessUser($this->recipient), 403);

        // Keep each purchase on its own row. Combine milestone entries by activity day and threshold.
        $query = RedPacketEntry::query()->from('red_packet_entries as entries')
            ->where('entries.user_id', $this->recipient->id)
            ->select('entries.earned_on', 'entries.source')
            ->selectRaw('MIN(entries.id) AS id, COUNT(*) AS chances')
            ->selectRaw('MAX(entries.invited_user_id) AS invited_user_id, MAX(entries.user_product_id) AS user_product_id')
            ->groupBy('entries.earned_on', 'entries.source')
            ->groupByRaw("CASE WHEN entries.source IN ('milestone_3', 'milestone_5') THEN NULL ELSE entries.id END")
            ->with(['invitee:id,username', 'purchase:id,product_id,meta,purchased_at']);

        return $table->query($query)->defaultKeySort(false)
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('entries.earned_on')->orderByRaw('MIN(entries.id) DESC'))
            ->columns([
                TextColumn::make('earned_on')->label(__('red_packet.activity_day')),
                TextColumn::make('source')->label(__('red_packet.grant_reason'))
                    ->formatStateUsing(fn (string $state): string => __('red_packet.'.match ($state) {
                        'invite' => 'invite_first_purchase',
                        'milestone_3', 'milestone_5' => $state.'_source',
                        default => $state,
                    })),
                TextColumn::make('chances')->label(__('red_packet.issued_total')),
                TextColumn::make('invitee.username')->label(__('red_packet.invited_member'))->placeholder('—'),
                TextColumn::make('product')->label(__('red_packet.product'))->placeholder('—')
                    ->state(function (RedPacketEntry $record): ?string {
                        $purchase = $record->purchase;
                        if (! $purchase) {
                            return null;
                        }
                        $labels = array_filter([$purchase->meta['product_code_snapshot'] ?? null, $purchase->meta['product_name_snapshot'] ?? null]);
                        return $labels ? implode(' · ', array_unique($labels)) : '#'.$purchase->product_id;
                    }),
                TextColumn::make('purchase.purchased_at')->label(__('filament.purchased_at'))
                    ->dateTime('Y-m-d H:i:s', config('app.timezone'))->placeholder('—'),
            ]);
    }

    private function applyIssuanceFilters(Table $table): Table
    {
        return $table->filters([
                SelectFilter::make('issuance')->label(__('red_packet.issuance_type'))
                    ->placeholder(__('red_packet.all_issuances'))->default('backfill')
                    ->options([
                        'backfill' => __('red_packet.confirmed_backfill'),
                        'live' => __('red_packet.live_issuance'),
                        'legacy' => __('red_packet.legacy_issuance'),
                    ])->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'backfill' => $query->where('entries.is_backfill', true),
                        'live' => $query->where('entries.is_backfill', false),
                        'legacy' => $query->whereNull('entries.is_backfill'),
                        default => $query,
                    }),
                Filter::make('issued_at')->schema([
                    DateTimePicker::make('from')->label(__('red_packet.issued_from')),
                    DateTimePicker::make('until')->label(__('red_packet.issued_until'))->afterOrEqual('from'),
                ])->columns(2)->columnSpan(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn ($q, $from) => $q->where('entries.created_at', '>=', $from))
                            ->when($data['until'] ?? null, fn ($q, $until) => $q->where('entries.created_at', '<=', $until));
                    }),
            ], layout: FiltersLayout::AboveContent)->filtersFormColumns(3);
    }
}
