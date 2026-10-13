<?php

namespace App\Filament\Pages\Auth;

use App\Models\Employee;
use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function getHeading(): string
    {
        return 'Masuk ke Sistem';
    }

    public function getSubheading(): ?string
    {
        return 'Karyawan: Gunakan ID Karyawan / NIK & PIN.';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getLoginFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('ID Karyawan / NIK')
            ->placeholder('Contoh: 2024.08.13.01')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('PIN')
            ->placeholder('Masukkan 6 Digit PIN')
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required()
            ->extraInputAttributes(['tabindex' => 2]);
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Ingat Saya')
            ->default(true);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();
            return null;
        }

        $data = $this->form->getState();
        $login = trim($data['login'] ?? '');
        $password = (string)($data['password'] ?? '');

        // 1. Cari User (Email untuk SuperAdmin atau ID Karyawan / NIK untuk karyawan)
        $user = null;

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            $user = User::where('email', $login)->first();
        }

        if (!$user) {
            $user = User::where('custom_id', $login)->first();
        }

        if (!$user) {
            $employee = Employee::where('custom_id', $login)->first();
            if ($employee) {
                $user = User::where('employee_id', $employee->id)->first()
                    ?? ($employee->email ? User::where('email', $employee->email)->first() : null);
            }
        }

        if (!$user) {
            $this->throwFailureValidationException();
        }

        // 2. Verifikasi Password / PIN
        $passwordMatches = Hash::check($password, $user->password);

        // Khusus Superadmin: izinkan password standar 'password', '123456', atau 'admin123'
        if (!$passwordMatches && $user->isSuperAdmin()) {
            if (in_array($password, ['password', '123456', 'admin123'])) {
                $passwordMatches = true;
                $user->password = Hash::make($password);
                $user->must_change_pin = false;
                $user->save();
            }
        }

        // Untuk pengguna non-superadmin yang BARU PERTAMA KALI login (belum pernah ubah PIN):
        // Izinkan initial_pin yang tersimpan jika password belum cocok
        if (!$passwordMatches && !$user->isSuperAdmin() && $user->must_change_pin && empty($user->pin_changed_at)) {
            if (($user->initial_pin && $password === $user->initial_pin) || ($password === '123456')) {
                $passwordMatches = true;
                $user->password = Hash::make($password);
                $user->must_change_pin = true;
                $user->save();
            }
        }

        if (!$passwordMatches) {
            $this->throwFailureValidationException();
        }

        Filament::auth()->login($user, $data['remember'] ?? false);

        if (
            ($user instanceof FilamentUser) &&
            (! $user->canAccessPanel(Filament::getCurrentPanel()))
        ) {
            Filament::auth()->logout();
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }
}
