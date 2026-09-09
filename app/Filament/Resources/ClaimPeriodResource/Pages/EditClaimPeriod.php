<?php

namespace App\Filament\Resources\ClaimPeriodResource\Pages;

use App\Filament\Resources\ClaimPeriodResource;
use App\Services\ClaimExcelExportService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditClaimPeriod extends EditRecord
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
            Actions\Action::make('mark_selesai')
                ->label('Tandai Selesai')
                ->icon('heroicon-o-check-circle')
                ->color('info')
                ->visible(fn () => $this->record->status === 'DRAFT')
                ->action(function () {
                    $this->record->update(['status' => 'SELESAI']);
                    Notification::make()->title('Status berhasil diubah ke SELESAI')->success()->send();
                }),
            Actions\Action::make('mark_diajukan')
                ->label('Tandai Diajukan')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => $this->record->status !== 'DIAJUKAN')
                ->action(function () {
                    $this->record->update(['status' => 'DIAJUKAN']);
                    Notification::make()->title('Status berhasil diubah ke DIAJUKAN')->success()->send();
                }),
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
