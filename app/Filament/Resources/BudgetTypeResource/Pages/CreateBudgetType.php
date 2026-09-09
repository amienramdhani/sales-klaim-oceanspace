<?php

namespace App\Filament\Resources\BudgetTypeResource\Pages;

use App\Filament\Resources\BudgetTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBudgetType extends CreateRecord
{
    protected static string $resource = BudgetTypeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
