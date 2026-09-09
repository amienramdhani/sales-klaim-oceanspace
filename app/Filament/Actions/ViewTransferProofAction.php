<?php

namespace App\Filament\Actions;

use App\Models\Claim;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Storage;

class ViewTransferProofAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'view_transfer_proof';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Bukti Transfer')
            ->icon('heroicon-o-photo')
            ->color('info')
            ->visible(fn (Claim $record) => !empty($record->transfer_proof_photo))
            ->modalHeading('Foto Bukti Transfer Pembayaran Finance')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(fn (Claim $record) => view('filament.components.transfer-proof-modal', [
                'photo' => $record->transfer_proof_photo,
                'record' => $record,
            ]))
            ->extraModalFooterActions([
                Action::make('download_file_direct')
                    ->label('Download Bukti Transfer')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (Claim $record) {
                        if ($record->transfer_proof_photo && Storage::disk('public')->exists($record->transfer_proof_photo)) {
                            return response()->download(Storage::disk('public')->path($record->transfer_proof_photo));
                        }
                    }),
            ]);
    }
}
