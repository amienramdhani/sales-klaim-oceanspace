<?php

namespace App\Filament\Resources\BbmReturnSaldoResource\Pages;

use App\Filament\Resources\BbmReturnSaldoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBbmReturnSaldos extends ListRecords
{
    protected static string $resource = BbmReturnSaldoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
