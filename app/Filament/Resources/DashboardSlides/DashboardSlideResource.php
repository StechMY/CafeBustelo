<?php

namespace App\Filament\Resources\DashboardSlides;

use App\Filament\Resources\DashboardSlides\Pages\CreateDashboardSlide;
use App\Filament\Resources\DashboardSlides\Pages\EditDashboardSlide;
use App\Filament\Resources\DashboardSlides\Pages\ListDashboardSlides;
use App\Filament\Resources\DashboardSlides\Pages\ViewDashboardSlide;
use App\Filament\Resources\DashboardSlides\Schemas\DashboardSlideForm;
use App\Filament\Resources\DashboardSlides\Schemas\DashboardSlideInfolist;
use App\Filament\Resources\DashboardSlides\Tables\DashboardSlidesTable;
use App\Models\DashboardSlide;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DashboardSlideResource extends Resource
{
    protected static ?string $model = DashboardSlide::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Platform Management';

    protected static ?string $navigationLabel = 'Dashboard Slides';

    protected static ?int $navigationSort = 70;

    public static function form(Schema $schema): Schema
    {
        return DashboardSlideForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DashboardSlideInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DashboardSlidesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDashboardSlides::route('/'),
            'create' => CreateDashboardSlide::route('/create'),
            'view' => ViewDashboardSlide::route('/{record}'),
            'edit' => EditDashboardSlide::route('/{record}/edit'),
        ];
    }
}