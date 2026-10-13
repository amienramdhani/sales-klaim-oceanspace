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
        if (empty($data['city']) && !empty($data['items'][0]['city'])) {
            $data['city'] = $data['items'][0]['city'];
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        if (!empty($data['items']) && is_array($data['items'])) {
            $firstItem = reset($data['items']);
            $data['purpose'] = $data['purpose'] ?? ($firstItem['purpose'] ?? '');
            $data['note'] = $data['note'] ?? ($firstItem['note'] ?? '');
            $data['amount'] = array_sum(array_column($data['items'], 'amount')) ?: ($data['amount'] ?? 0);

            // Bidirectional sync for city
            $firstCity = $firstItem['city'] ?? null;
            if (empty($data['city']) && !empty($firstCity)) {
                $data['city'] = $firstCity;
            }
            if (!empty($data['city'])) {
                foreach ($data['items'] as $idx => $it) {
                    if (empty($it['city'])) {
                        $data['items'][$idx]['city'] = $data['city'];
                    }
                }
            }

            // Sync custom bank if filled in Karangan Bunga item
            foreach ($data['items'] as $idx => $it) {
                if (($it['entertain_subtype'] ?? '') === 'Karangan Bunga' || str_contains(strtolower($it['purpose'] ?? ''), 'karangan bunga')) {
                    if (!empty($it['custom_bank_account_name']) && empty($data['custom_bank_account_name'])) {
                        $data['custom_bank_account_name'] = $it['custom_bank_account_name'];
                    }
                    if (!empty($it['custom_bank_name']) && empty($data['custom_bank_name'])) {
                        $data['custom_bank_name'] = $it['custom_bank_name'];
                    }
                    if (!empty($it['custom_bank_account_number']) && empty($data['custom_bank_account_number'])) {
                        $data['custom_bank_account_number'] = $it['custom_bank_account_number'];
                    }
                }

                // Auto-fill attendance perihal, tempat, and tanggal from item data so no double input
                if (empty($it['attendance_purpose']) && !empty($it['purpose'])) {
                    $data['items'][$idx]['attendance_purpose'] = $it['purpose'];
                }
                if (empty($it['attendance_place']) && !empty($it['note'])) {
                    $data['items'][$idx]['attendance_place'] = $it['note'];
                }
                if (empty($it['attendance_date']) && !empty($it['receipt_date'])) {
                    $data['items'][$idx]['attendance_date'] = $it['receipt_date'];
                }
            }
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
