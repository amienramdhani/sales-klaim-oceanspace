<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'MASTER DATA';

    protected static ?string $modelLabel = 'Pengguna & ID User';

    protected static ?string $pluralModelLabel = 'Data Pengguna & ID User';

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Pengguna')
                    ->description('ID User dapat dikustomisasi dengan kombinasi huruf dan angka sesuai kebutuhan sistem')
                    ->schema([
                        Forms\Components\TextInput::make('custom_id')
                            ->label('ID User (Kombinasi Huruf & Angka)')
                            ->placeholder('Contoh: ADM-001 / RGM-001 / ASM-PWT01 / JEJEN-01')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->helperText('Format bebas: kombinasi huruf dan angka unik.'),

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Pengguna')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Hendra Setia Permana'),

                        Forms\Components\Select::make('role_id')
                            ->label('Role / Peran Pengguna')
                            ->relationship('role', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->helperText('Menentukan hak akses approval dan plafon klaim operasional.'),

                        Forms\Components\Select::make('employee_id')
                            ->label('Tautkan ke Data Karyawan (Opsional)')
                            ->relationship('employee', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Menghubungkan akun login dengan data profil karyawan & tanda tangan.'),

                        Forms\Components\TextInput::make('homebase')
                            ->label('Kota Asal / Homebase')
                            ->default('Purwokerto')
                            ->required()
                            ->placeholder('Contoh: Purwokerto / Semarang / Jakarta')
                            ->helperText('Titik acuan minimal 80 km untuk perjalanan dinas.'),

                        Forms\Components\Select::make('region')
                            ->label('Data Region Operasional')
                            ->placeholder('Pilih region operasional')
                            ->helperText('Region operasional pengguna (untuk ASM, RGM, Sales, Finance, dll).')
                            ->options(self::getAvailableRegions())
                            ->searchable()
                            ->preload()
                            ->visible(function (Forms\Get $get, ?User $record) {
                                $roleId = $get('role_id') ?? $record?->role_id;
                                if (!$roleId && $record) return !$record->isAdmin();
                                if (!$roleId) return true;
                                $role = Role::find($roleId);
                                return !$role || !in_array(strtoupper($role->code ?? $role->name), ['ADMIN', 'SUPERADMIN', 'ADMINISTRATOR']);
                            }),

                        Forms\Components\Select::make('managed_regions')
                            ->label('Region yang Dikelola (Bisa Lebih Dari Satu)')
                            ->placeholder('Pilih satu atau beberapa region yang dipegang Admin')
                            ->helperText('Role Admin dapat mengelola lebih dari satu region (misal: CIREBON, JATENG, JABO). Klaim dari seluruh region yang dipilih akan masuk untuk diperiksa oleh Admin ini.')
                            ->options(self::getAvailableRegions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->visible(function (Forms\Get $get, ?User $record) {
                                $roleId = $get('role_id') ?? $record?->role_id;
                                if (!$roleId && $record) return $record->isAdmin();
                                if (!$roleId) return false;
                                $role = Role::find($roleId);
                                return $role && in_array(strtoupper($role->code ?? $role->name), ['ADMIN', 'SUPERADMIN', 'ADMINISTRATOR']);
                            }),
                    ])->columns(2),

                Forms\Components\Section::make('Notifikasi Telegram')
                    ->description('ID Telegram digunakan untuk mengirimkan notifikasi pengajuan dan approval klaim langsung ke pengguna')
                    ->schema([
                        Forms\Components\TextInput::make('telegram_chat_id')
                            ->label('Telegram Chat ID')
                            ->placeholder('Contoh: 123456789 atau -1001234567890')
                            ->helperText('Chat ID akun Telegram pengguna. Untuk mengetahui Chat ID: cari bot Anda di Telegram lalu klik Start, atau gunakan bot @userinfobot.')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('telegram_username')
                            ->label('Username Telegram (Opsional)')
                            ->placeholder('Contoh: @hendra_msi')
                            ->helperText('Sebagai penanda kontak Telegram pengguna.')
                            ->maxLength(100),
                    ])->columns(2),

                Forms\Components\Section::make('Autentikasi Login')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('user@company.com'),

                        Forms\Components\TextInput::make('password')
                            ->label('Kata Sandi')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255)
                            ->placeholder('Minimal 8 karakter')
                            ->helperText('Kosongkan jika tidak ingin mengubah kata sandi saat edit.'),
                    ])->columns(2),
            ]);
    }

    public static function getAvailableRegions(): array
    {
        return [
            'CIREBON' => 'CIREBON',
            'JABO' => 'JABO',
            'JABODETABEK' => 'JABODETABEK',
            'JATENG' => 'JATENG',
            'JATIM' => 'JATIM',
            'JOGJA' => 'JOGJA',
            'PURWOKERTO' => 'PURWOKERTO',
            'JAKARTA' => 'JAKARTA',
            'SUMATERA' => 'SUMATERA',
            'SUMATERA BARAT' => 'SUMATERA BARAT',
            'SUMATRA UTARA & ACEH' => 'SUMATRA UTARA & ACEH',
            'SULAWESI' => 'SULAWESI',
            'LAMPUNG & BENGKULU' => 'LAMPUNG & BENGKULU',
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('custom_id')
                    ->label('ID User')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pengguna')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('region_display')
                    ->label('Region / Wilayah')
                    ->badge()
                    ->color('info')
                    ->state(function (User $record) {
                        if ($record->isAdmin()) {
                            $list = $record->getManagedRegionsList();
                            return !empty($list) ? $list : ($record->region ? array_map('trim', explode(',', $record->region)) : ['-']);
                        }
                        return $record->region ? [$record->region] : ($record->homebase ? [$record->homebase] : ['-']);
                    })
                    ->separator(', '),
                Tables\Columns\TextColumn::make('telegram_chat_id')
                    ->label('Telegram ID')
                    ->badge()
                    ->color(fn ($state) => filled($state) ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => filled($state) ? "ID: {$state}" : 'Belum Ada')
                    ->searchable(),
                Tables\Columns\TextColumn::make('homebase')
                    ->label('Homebase')
                    ->searchable()
                    ->default('Purwokerto')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Terdaftar')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role_id')
                    ->label('Filter Role')
                    ->relationship('role', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('test_telegram')
                    ->label('Tes Telegram')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn (User $record) => filled($record->telegram_chat_id))
                    ->requiresConfirmation()
                    ->modalHeading('Kirim Pesan Uji Coba Telegram')
                    ->modalDescription(fn (User $record) => "Kirim pesan tes ke akun Telegram {$record->name} (Chat ID: {$record->telegram_chat_id})?")
                    ->action(function (User $record) {
                        $telegram = app(\App\Services\TelegramService::class);
                        if (!$telegram->isEnabled()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Bot Telegram Belum Dikonfigurasi')
                                ->body('Isi TELEGRAM_BOT_TOKEN di file .env terlebih dahulu.')
                                ->warning()
                                ->send();
                            return;
                        }

                        $testMsg = "👋 <b>Uji Coba Notifikasi Sistem Klaim MSI</b>\n\n"
                                 . "Halo <b>{$record->name}</b>!\n"
                                 . "Akun Telegram Anda (ID: <code>{$record->telegram_chat_id}</code>) telah berhasil terhubung dengan sistem notifikasi pengajuan & persetujuan klaim operasional.\n\n"
                                 . "⏰ Waktu Uji Coba: " . date('d/m/Y H:i:s');

                        $success = $telegram->sendMessage($record->telegram_chat_id, $testMsg);

                        if ($success) {
                            \Filament\Notifications\Notification::make()
                                ->title('Pesan Tes Berhasil Terkirim!')
                                ->body("Notifikasi berhasil dikirimkan ke Telegram {$record->name}.")
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('Gagal Mengirim Pesan')
                                ->body("Pastikan user telah mengirim /start ke Bot Telegram dan Chat ID sudah benar.")
                                ->danger()
                                ->send();
                        }
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
