<?php

namespace App\Filament\Resources\ClaimPeriodResource\Pages;

use App\Filament\Resources\ClaimPeriodResource;
use App\Services\ClaimExcelExportService;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewClaimPeriod extends ViewRecord
{
    protected static string $resource = ClaimPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                ->action(function () {
                    return app(ClaimExcelExportService::class)->export($this->record);
                }),
            Actions\EditAction::make(),
        ];
    }
}
