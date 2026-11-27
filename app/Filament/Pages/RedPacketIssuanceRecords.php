<?php

namespace App\Filament\Pages;

use App\Filament\Resources\RedPacketDraws\RedPacketDrawResource;
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
use UnitEnum;

class RedPacketIssuanceRecords extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.red-packet-issuance-records';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?int $navigationSort = 29;

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
        return static::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return __('red_packet.issuance_help', ['timezone' => config('app.timezone')]);
    }

    public function table(Table $table): Table
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
                TextColumn::make('username')->label(__('filament.username'))->searchable()->sortable(),
                TextColumn::make('chances')->label(__('red_packet.issued_total'))->sortable(),
                TextColumn::make('invites')->label(__('red_packet.invite_first_purchase')),
                TextColumn::make('bonus_three')->label(__('red_packet.milestone_3_source')),
                TextColumn::make('bonus_five')->label(__('red_packet.milestone_5_source')),
                TextColumn::make('upgrades')->label(__('red_packet.upgrade')),
            ])->defaultSort('chances', 'desc')
            ->filters([
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
