<?php

namespace App\Filament\Resources\WithdrawRequests\Schemas;

use Filament\Schemas\Schema;

class WithdrawRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }
}