<?php

namespace App\Filament\Resources\AttendanceDocResource\Pages;

use App\Filament\Resources\AttendanceDocResource;
use App\Services\AttendanceWordExportService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceDoc extends EditRecord
{
    protected static string $resource = AttendanceDocResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_word')
                ->label('Download Word (.docx)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->action(fn () => app(AttendanceWordExportService::class)->export($this->record)),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
