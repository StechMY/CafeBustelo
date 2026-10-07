<?php

namespace App\Filament\Resources\PageImages\Pages;

use App\Filament\Resources\PageImages\PageImageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePageImages extends ManageRecords
{
    protected static string $resource = PageImageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
