<?php

namespace App\Filament\Actions;

use App\Models\Claim;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;

class UploadReturnTransferProofAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'upload_return_transfer_proof';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Kirim Bukti Transfer Balik')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('warning')
            ->visible(function (Claim $record) {
                // Jika sudah ada bukti transfer balik yang diunggah, tombol dihilangkan
                if (!empty($record->return_transfer_proof)) {
                    return false;
                }

                $user = auth()->user();
                if (!$user) return false;

                // Check authorization: User (owner), Admin, or Super Admin
                $isOwner = ($record->employee?->user_id === $user->id) || ($record->employee?->custom_id === $user->custom_id);
                $canUpload = $isOwner || $user->isAdmin() || $user->isSuperAdmin();

                if (!$canUpload) return false;

                // Only show if there is a remaining budget to return
                $budget = (float)($record->effective_employee?->bbm_budget ?? 0);
                $used = (float)$record->amount;

                return ($budget > 0) && ($used < $budget);
            })
            ->modalHeading('Upload Bukti Transfer Pengembalian Sisa Budget ke Finance')
            ->modalDescription(function (Claim $record) {
                $budget = (float)($record->effective_employee?->bbm_budget ?? 0);
                $used = (float)$record->amount;
                $sisa = max(0, $budget - $used);
                return "Karyawan menerima budget awal sebesar Rp " . number_format($budget, 0, ',', '.') . " dan total klaim sebesar Rp " . number_format($used, 0, ',', '.') . ". Sisa dana yang wajib dikembalikan ke Finance adalah sebesar Rp " . number_format($sisa, 0, ',', '.') . ".";
            })
            ->form(function (Claim $record) {
                $budget = (float)($record->effective_employee?->bbm_budget ?? 0);
                $used = (float)$record->amount;
                $sisa = max(0, $budget - $used);

                return [
                    Forms\Components\TextInput::make('return_transfer_amount')
                        ->label('Nominal Dana Ditransfer Balik (Rp)')
                        ->numeric()
                        ->prefix('Rp')
                        ->required()
                        ->default($sisa)
                        ->helperText('Nominal sisa yang sudah ditransfer kembali ke rekening Finance.'),

                    Forms\Components\FileUpload::make('return_transfer_proof')
                        ->label('Foto Struk / Tangkapan Layar Bukti Transfer')
                        ->image()
                        ->disk('public')
                        ->directory('return-transfer-proofs')
                        ->visibility('public')
                        ->required()
                        ->openable()
                        ->downloadable()
                        ->helperText('Unggah foto struk ATM, bukti transfer m-banking, atau kuitansi setor tunai kasir.'),

                    Forms\Components\Textarea::make('return_transfer_notes')
                        ->label('Catatan Tambahan (Opsional)')
                        ->placeholder('Contoh: Transfer via BCA Finance a.n PT Media Selular Indonesia')
                        ->rows(2),
                ];
            })
            ->action(function (Claim $record, array $data) {
                $record->update([
                    'return_transfer_proof' => $data['return_transfer_proof'],
                    'return_transfer_amount' => $data['return_transfer_amount'],
                    'return_transferred_at' => now(),
                    'return_transfer_by_id' => auth()->id(),
                    'return_transfer_notes' => $data['return_transfer_notes'] ?? null,
                ]);

                Notification::make()
                    ->title('Bukti Transfer Balik Berhasil Disimpan!')
                    ->body('Bukti pengembalian sisa dana operasional telah berhasil dicatat dan dapat diverifikasi oleh Finance.')
                    ->success()
                    ->send();
            });
    }
}
