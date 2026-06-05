<?php

namespace App\Filament\Resources\SvipPackages\Pages;

use App\Filament\Resources\SvipPackages\SvipPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSvipPackages extends ListRecords
{
    protected static string $resource = SvipPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}