<?php

namespace App\Filament\Resources\ClaimPeriodResource\Pages;

use App\Filament\Resources\ClaimPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClaimPeriods extends ListRecords
{
    protected static string $resource = ClaimPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Rekap Periode Baru'),
        ];
    }
}
