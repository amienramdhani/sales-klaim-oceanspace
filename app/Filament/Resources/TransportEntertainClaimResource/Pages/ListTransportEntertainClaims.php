<?php

namespace App\Filament\Resources\TransportEntertainClaimResource\Pages;

use App\Filament\Resources\TransportEntertainClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransportEntertainClaims extends ListRecords
{
    protected static string $resource = TransportEntertainClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Klaim Transport/Entertain'),
        ];
    }
}
