<?php

namespace App\Filament\Actions;

use App\Models\Claim;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Storage;

class ViewReturnTransferProofAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'view_return_transfer_proof';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Bukti Transfer Balik')
            ->icon('heroicon-o-document-check')
            ->color('success')
            ->visible(fn (Claim $record) => !empty($record->return_transfer_proof))
            ->modalHeading('Foto / Dokumen Bukti Transfer Pengembalian Sisa Dana ke Finance')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->modalContent(fn (Claim $record) => view('filament.components.return-transfer-proof-modal', [
                'photo' => $record->return_transfer_proof,
                'record' => $record,
            ]))
            ->extraModalFooterActions([
                Action::make('download_return_file')
                    ->label('Download Bukti Transfer Balik')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (Claim $record) {
                        if ($record->return_transfer_proof && Storage::disk('public')->exists($record->return_transfer_proof)) {
                            return response()->download(Storage::disk('public')->path($record->return_transfer_proof));
                        }
                    }),
            ]);
    }
}
