<?php

namespace App\Filament\Resources\ClaimResource\Pages;

use App\Filament\Resources\ClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClaim extends EditRecord
{
    protected static string $resource = ClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn () => app(\App\Services\ClaimPdfExportService::class)->exportSingleClaimPdf($this->record)),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Jika klaim sebelumnya ditolak, ketika admin/user memperbaiki dan menyimpannya,
        // status otomatis berubah menjadi SEDANG_DIREVISI
        if ($this->record && $this->record->approval_status === 'DITOLAK') {
            $data['approval_status'] = 'SEDANG_DIREVISI';
        }

        return $data;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        if ($this->record && in_array($this->record->approval_status, ['DITOLAK', 'SEDANG_DIREVISI'])) {
            return 'Data klaim berhasil diperbarui dan status menjadi Sedang Direvisi. Silakan klik Ajukan Kembali!';
        }

        return 'Perubahan berhasil disimpan!';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
