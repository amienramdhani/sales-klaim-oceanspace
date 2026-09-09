<?php

namespace App\Filament\Resources\BbmClaimResource\Pages;

use App\Filament\Resources\BbmClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBbmClaims extends ListRecords
{
    protected static string $resource = BbmClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Klaim BBM'),
        ];
    }
}
