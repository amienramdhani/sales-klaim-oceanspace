<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;

class ChangePin extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?string $title = 'Ubah PIN Pertama Kali';
    protected static string $view = 'filament.pages.change-pin';

    public ?array $data = [];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $user = auth()->user();
        if (!$user || !$user->must_change_pin) {
            $this->redirectRoute('filament.admin.pages.dashboard');
            return;
        }

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Keamanan Akun — Buat PIN Baru')
                    ->description('Untuk keamanan akun Anda, silakan ubah PIN default Anda menjadi 6 digit angka rahasia sebelum melanjutkan.')
                    ->schema([
                        TextInput::make('new_pin')
                            ->label('PIN Baru (6 Digit Angka)')
                            ->password()
                            ->numeric()
                            ->length(6)
                            ->required()
                            ->placeholder('Contoh: 123456')
                            ->revealable()
                            ->helperText('Gunakan 6 digit angka yang mudah Anda ingat namun sulit ditebak orang lain.'),

                        TextInput::make('new_pin_confirmation')
                            ->label('Konfirmasi PIN Baru')
                            ->password()
                            ->numeric()
                            ->length(6)
                            ->required()
                            ->same('new_pin')
                            ->placeholder('Ketik ulang 6 digit PIN baru')
                            ->revealable(),
                    ]),
            ])
            ->statePath('data');
    }

    public function updatePin(): void
    {
        $state = $this->form->getState();
        $user = \App\Models\User::find(auth()->id());
        if ($user) {
            $user->password = Hash::make($state['new_pin']);
            $user->must_change_pin = false;
            $user->pin_changed_at = now();
            $user->initial_pin = null;
            $user->save();
        }

        Notification::make()
            ->title('PIN Berhasil Diperbarui')
            ->body('PIN akun Anda telah berhasil disimpan. Selamat datang di sistem Sales Klaim!')
            ->success()
            ->send();

        $this->redirectRoute('filament.admin.pages.dashboard');
    }
}
