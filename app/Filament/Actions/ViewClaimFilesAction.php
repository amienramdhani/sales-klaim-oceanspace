<?php

namespace App\Filament\Actions;

use App\Models\Claim;
use Filament\Tables\Actions\Action;

class ViewClaimFilesAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'view_claim_files';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(function (Claim $record) {
            $count = $record->getAllAttachedFilesCount();
            return "Berkas ({$count})";
        })
        ->icon('heroicon-o-paper-clip')
        ->color(fn (Claim $record) => $record->getAllAttachedFilesCount() > 0 ? 'info' : 'gray')
        ->tooltip(fn (Claim $record) => "Lihat {$record->getAllAttachedFilesCount()} berkas/foto nota, kwitansi, dan lampiran klaim ini")
        ->modalHeading(fn (Claim $record) => "Berkas Lampiran: " . ($record->effective_employee?->name ?? 'Pemohon') . " — " . ($record->claim_type_string ?? 'Klaim'))
        ->modalSubmitAction(false)
        ->modalCancelActionLabel('Tutup')
        ->modalWidth('5xl')
        ->modalContent(fn (Claim $record) => view('filament.components.claim-files-modal', [
            'record' => $record,
            'files' => $record->getAllAttachedFiles(),
        ]));
    }
}
