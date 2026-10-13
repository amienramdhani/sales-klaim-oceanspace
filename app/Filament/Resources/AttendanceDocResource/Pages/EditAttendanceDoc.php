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
            Actions\Action::make('download_excel')
                ->label('Download Excel (.xlsx)')
                ->icon('heroicon-o-table-cells')
                ->color('success')
                ->action(function () {
                    $record = $this->record;
                    if ($record->claim) {
                        return app(\App\Services\ClaimExcelExportService::class)->exportSingleClaim($record->claim);
                    }
                    $tempClaim = new \App\Models\Claim([
                        'claim_category' => 'transport_entertain',
                        'claim_type' => 'Entertain',
                        'user_id' => $record->user_id,
                        'employee_id' => $record->employee_id,
                        'claim_date' => $record->date ?? now(),
                        'purpose' => $record->purpose,
                        'note' => $record->place,
                        'amount' => $record->estimated_amount ?? 0,
                        'photos' => array_merge((array)($record->receipt_photos ?? []), (array)($record->photos ?? [])),
                        'items' => [[
                            'claim_type' => 'Entertain',
                            'entertain_subtype' => $record->category === 'Ulang Tahun' ? 'Lainnya' : 'Makan',
                            'receipt_date' => $record->date ? $record->date->toDateString() : now()->toDateString(),
                            'purpose' => $record->purpose,
                            'note' => $record->place,
                            'amount' => $record->estimated_amount ?? 0,
                            'attendance_purpose' => $record->purpose,
                            'attendance_place' => $record->place,
                            'attendance_date' => $record->date ? $record->date->toDateString() : now()->toDateString(),
                            'attendance_participants' => $record->participants->map(fn ($p) => ['name' => $p->name, 'position' => $p->position, 'status' => $p->signature_info ?? 'HADIR'])->toArray(),
                            'attendance_photos' => $record->photos ?? [],
                        ]],
                    ]);
                    $tempClaim->id = 0;
                    return app(\App\Services\ClaimExcelExportService::class)->exportSingleClaim($tempClaim);
                }),
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
