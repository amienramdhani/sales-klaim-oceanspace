<?php

namespace App\Filament\Resources\BudgetTypeResource\Pages;

use App\Filament\Resources\BudgetTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBudgetType extends EditRecord
{
    protected static string $resource = BudgetTypeResource::class;

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
