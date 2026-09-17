<?php

namespace App\Filament\Resources\TransportEntertainClaimResource\Pages;

use App\Filament\Resources\TransportEntertainClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTransportEntertainClaim extends EditRecord
{
    protected static string $resource = TransportEntertainClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn () => app(\App\Services\ClaimPdfExportService::class)->exportTransportEntertainSinglePdf($this->record)),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (empty($data['purpose']) && !empty($data['items'][0]['purpose'])) {
            $data['purpose'] = $data['items'][0]['purpose'];
        }
        if (empty($data['note']) && !empty($data['items'][0]['note'])) {
            $data['note'] = $data['items'][0]['note'];
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        if ($user && $user->isFieldUser() && !$user->isAdmin() && !$user->isSuperAdmin()) {
            $data['amount'] = $data['amount'] ?? 0;
            $data['items'] = [
                [
                    'claim_type' => 'Entertain',
                    'entertain_subtype' => 'Makan',
                    'receipt_date' => $data['claim_date'] ?? now()->toDateString(),
                    'purpose' => $data['purpose'] ?? '',
                    'note' => $data['note'] ?? '',
                    'amount' => $data['amount'] ?? 0,
                ]
            ];
        }

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
