<?php

namespace App\Filament\Resources\AttendanceDocResource\Pages;

use App\Filament\Resources\AttendanceDocResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceDocs extends ListRecords
{
    protected static string $resource = AttendanceDocResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buat Daftar Hadir Meeting'),
        ];
    }
}
