<?php

namespace App\Filament\Resources\BbmBeritaAcaraResource\Pages;

use App\Filament\Resources\BbmBeritaAcaraResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBbmBeritaAcaras extends ListRecords
{
    protected static string $resource = BbmBeritaAcaraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Buat Berita Acara BBM Baru'),
        ];
    }
}
