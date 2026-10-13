<?php

namespace App\Filament\Resources\AttendanceDocResource\Pages;

use App\Filament\Resources\AttendanceDocResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceDoc extends CreateRecord
{
    protected static string $resource = AttendanceDocResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        $data['user_id'] = $data['user_id'] ?? $user?->id;
        $data['employee_id'] = $data['employee_id'] ?? $user?->getEffectiveEmployeeId();
        $data['category'] = $data['category'] ?? 'Makan';
        $data['admin_status'] = $data['admin_status'] ?? 'MENUNGGU_PENYESUAIAN';

        return $data;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Dokumen Daftar Hadir Berhasil Disimpan!';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
