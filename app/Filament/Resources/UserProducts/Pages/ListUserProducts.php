<?php

namespace App\Filament\Resources\UserProducts\Pages;

use App\Filament\Resources\UserProducts\UserProductResource;
use Filament\Resources\Pages\ListRecords;

class ListUserProducts extends ListRecords
{
    protected static string $resource = UserProductResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
