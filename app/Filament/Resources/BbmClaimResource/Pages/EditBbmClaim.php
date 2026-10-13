<?php

namespace App\Filament\Resources\BbmClaimResource\Pages;

use App\Filament\Resources\BbmClaimResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBbmClaim extends EditRecord
{
    protected static string $resource = BbmClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('danger')
                ->action(fn () => app(\App\Services\ClaimPdfExportService::class)->exportBbmSinglePdf($this->record)),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (empty($data['fuel_start_km']) && !empty($data['items'][0]['fuel_start_km'])) {
            $data['fuel_start_km'] = $data['items'][0]['fuel_start_km'];
        }
        if (empty($data['fuel_type']) && !empty($data['items'][0]['fuel_type'])) {
            $data['fuel_type'] = $data['items'][0]['fuel_type'];
        }
        if (empty($data['photos'])) {
            $photos = [];
            if (!empty($data['bbm_photo_before'])) $photos[] = $data['bbm_photo_before'];
            if (!empty($data['bbm_photo_after'])) $photos[] = $data['bbm_photo_after'];
            if (!empty($photos)) $data['photos'] = $photos;
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        if (!empty($data['items']) && is_array($data['items'])) {
            $firstItem = reset($data['items']);
            $data['fuel_start_km'] = $data['fuel_start_km'] ?? ($firstItem['fuel_start_km'] ?? null);
            $data['fuel_type'] = $data['fuel_type'] ?? ($firstItem['fuel_type'] ?? 'Pertalite');
            $data['bbm_photo_before'] = $data['bbm_photo_before'] ?? ($firstItem['bbm_photo_before'] ?? null);
            $data['bbm_photo_after'] = $data['bbm_photo_after'] ?? ($firstItem['bbm_photo_after'] ?? null);
            $totalAmount = array_sum(array_column($data['items'], 'amount'));
            $totalBase = array_sum(array_column($data['items'], 'fuel_base_amount'));
            $data['amount'] = $totalAmount ?: ($data['amount'] ?? 0);
            $data['fuel_base_amount'] = $totalBase ?: ($data['fuel_base_amount'] ?? $data['amount']);
        }

        if ($this->record && $this->record->approval_status === 'DITOLAK') {
            $data['approval_status'] = 'SEDANG_DIREVISI';
        }

        // Validasi Over Budget BBM: Wajib lampirkan Berita Acara (BA)
        $empId = $data['employee_id'] ?? $this->record?->employee_id;
        $emp = \App\Models\Employee::find($empId);
        $budget = $emp ? (float)$emp->bbm_budget : 0.0;
        $totalAmount = (float)($data['amount'] ?? 0);

        if ($budget > 0 && $totalAmount > $budget) {
            $data['return_transfer_amount'] = null;
            $data['return_transfer_proof'] = null;
            $data['return_transfer_notes'] = null;

            $proof = $data['ba_wa_proof_photo'] ?? $this->record?->ba_wa_proof_photo;
            if (empty($proof)) {
                \Filament\Notifications\Notification::make()
                    ->title('Wajib Melampirkan Berita Acara (BA)')
                    ->body('Pengeluaran BBM sebesar Rp ' . number_format($totalAmount, 0, ',', '.') . ' melebihi budget plafon bulanan (Rp ' . number_format($budget, 0, ',', '.') . '). Anda wajib membuat Berita Acara (BA) dan mengunggah bukti persetujuannya.')
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
            }
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
