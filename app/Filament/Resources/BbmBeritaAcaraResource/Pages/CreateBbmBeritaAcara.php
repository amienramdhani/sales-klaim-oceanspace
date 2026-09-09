<?php

namespace App\Filament\Resources\BbmBeritaAcaraResource\Pages;

use App\Filament\Resources\BbmBeritaAcaraResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBbmBeritaAcara extends CreateRecord
{
    protected static string $resource = BbmBeritaAcaraResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
