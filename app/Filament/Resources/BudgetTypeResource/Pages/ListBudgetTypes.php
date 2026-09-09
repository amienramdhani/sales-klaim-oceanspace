<?php

namespace App\Filament\Resources\BudgetTypeResource\Pages;

use App\Filament\Resources\BudgetTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBudgetTypes extends ListRecords
{
    protected static string $resource = BudgetTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Jenis Budget'),
        ];
    }
}
