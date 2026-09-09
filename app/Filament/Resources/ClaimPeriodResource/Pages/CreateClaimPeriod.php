<?php

namespace App\Filament\Resources\ClaimPeriodResource\Pages;

use App\Filament\Resources\ClaimPeriodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClaimPeriod extends CreateRecord
{
    protected static string $resource = ClaimPeriodResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
