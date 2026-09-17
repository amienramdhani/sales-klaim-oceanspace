<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

class EditProfile extends BaseEditProfile
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                Section::make('Notifikasi Telegram')
                    ->description('Hubungkan akun Telegram Anda untuk menerima notifikasi pengajuan & persetujuan klaim secara langsung (Private Chat).')
                    ->schema([
                        TextInput::make('telegram_chat_id')
                            ->label('Telegram Chat ID')
                            ->placeholder('Contoh: 123456789')
                            ->helperText('Untuk mengetahui Chat ID Anda: Cari bot Telegram kantor lalu klik Start (/start), atau ketik ke @userinfobot.')
                            ->maxLength(100),
                        TextInput::make('telegram_username')
                            ->label('Username Telegram (Opsional)')
                            ->placeholder('Contoh: @username_anda')
                            ->maxLength(100),
                    ])->columns(2),
            ]);
    }
}
