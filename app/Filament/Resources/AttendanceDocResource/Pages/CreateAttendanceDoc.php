<?php

namespace App\Filament\Resources\AttendanceDocResource\Pages;

use App\Filament\Resources\AttendanceDocResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceDoc extends CreateRecord
{
    protected static string $resource = AttendanceDocResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
