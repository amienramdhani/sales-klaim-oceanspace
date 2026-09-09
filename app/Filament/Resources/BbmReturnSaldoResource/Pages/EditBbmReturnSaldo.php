<?php

namespace App\Filament\Resources\BbmReturnSaldoResource\Pages;

use App\Filament\Resources\BbmReturnSaldoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBbmReturnSaldo extends EditRecord
{
    protected static string $resource = BbmReturnSaldoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
