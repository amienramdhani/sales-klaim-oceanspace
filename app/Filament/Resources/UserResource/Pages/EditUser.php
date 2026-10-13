<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reset_pin')
                ->label('Reset PIN Pengguna')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(fn () => "Reset PIN: {$this->record->name}")
                ->modalDescription('Sistem akan membuatkan 6 digit PIN baru dan mewajibkan pengguna mengganti PIN saat login berikutnya.')
                ->action(function () {
                    $newPin = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    $this->record->update([
                        'password' => \Illuminate\Support\Facades\Hash::make($newPin),
                        'initial_pin' => $newPin,
                        'must_change_pin' => true,
                    ]);

                    \Filament\Notifications\Notification::make()
                        ->title('PIN Berhasil Direset!')
                        ->body("PIN Baru untuk {$this->record->name} (ID: {$this->record->custom_id}) adalah: {$newPin}. Pengguna diwajibkan membuat PIN baru saat login.")
                        ->success()
                        ->persistent()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
