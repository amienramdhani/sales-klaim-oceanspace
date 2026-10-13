<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $pin = !empty($data['initial_pin']) ? $data['initial_pin'] : str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $data['initial_pin'] = $pin;
        $data['password'] = \Illuminate\Support\Facades\Hash::make($pin);
        $data['must_change_pin'] = true;

        if (empty($data['email'])) {
            $prefix = !empty($data['custom_id']) ? preg_replace('/[^a-zA-Z0-9]/', '', $data['custom_id']) : 'user_' . time();
            $data['email'] = strtolower($prefix) . '@msi.co.id';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        \Filament\Notifications\Notification::make()
            ->title('Akun Pengguna Berhasil Dibuat!')
            ->body("ID Karyawan / NIK: {$this->record->custom_id} | PIN Awal: {$this->record->initial_pin}. Pengguna wajib membuat PIN baru saat pertama kali login.")
            ->success()
            ->persistent()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
