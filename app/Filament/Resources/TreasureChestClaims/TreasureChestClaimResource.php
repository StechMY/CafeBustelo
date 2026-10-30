<?php

namespace App\Filament\Resources\TreasureChestClaims;

use App\Filament\Resources\TreasureChestClaims\Pages\ManageTreasureChestClaims;
use App\Models\TreasureChestClaim;
use App\Support\FilamentSupportScope;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TreasureChestClaimResource extends Resource
{
    protected static ?string $model = TreasureChestClaim::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';
    protected static ?int $navigationSort = 26;

    public static function canViewAny(): bool
    {
        $admin = Filament::auth()->user();

        return $admin && ($admin->hasRole('super_admin') || $admin->can('Manage:TreasureChest'));
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('filament.platform_management');
    }

    public static function getModelLabel(): string
    {
        return __('treasure_chest.title');
    }

    public static function getPluralModelLabel(): string
    {
        return __('treasure_chest.title');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.username')->label(__('treasure_chest.member'))->searchable(),
            TextColumn::make('chest_number')->label(__('treasure_chest.chest'))->sortable(),
            TextColumn::make('threshold')->label(__('treasure_chest.threshold')),
            TextColumn::make('amount')->label(__('golden_egg.cash_amount'))->numeric()->suffix(' VND'),
            TextColumn::make('keys')->label(__('treasure_chest.keys')),
            TextColumn::make('discount_percent')->label(__('golden_egg.discount_percent'))->suffix('%'),
            TextColumn::make('created_at')->label(__('treasure_chest.claimed_at'))->dateTime()->sortable(),
        ])->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return FilamentSupportScope::applyToUserOwnedQuery(parent::getEloquentQuery()->with('user'));
    }

    public static function getPages(): array
    {
        return ['index' => ManageTreasureChestClaims::route('/')];
    }
}
