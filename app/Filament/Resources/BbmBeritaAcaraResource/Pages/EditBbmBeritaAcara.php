<?php

namespace App\Filament\Resources\BbmBeritaAcaraResource\Pages;

use App\Filament\Resources\BbmBeritaAcaraResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBbmBeritaAcara extends EditRecord
{
    protected static string $resource = BbmBeritaAcaraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
