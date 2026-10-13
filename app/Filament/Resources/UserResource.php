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
use Illuminate\Database\Eloquent\Builder;
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

    public static function canCreate(): bool
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
                            ->label('ID Karyawan / NIK (Untuk Login)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('Contoh: 2024.08.13.01 / ADM-001')
                            ->helperText('ID unik ini digunakan sebagai username saat login ke sistem.'),

                        Forms\Components\Select::make('employee_id')
                            ->label('Hubungkan ke Data Karyawan')
                            ->placeholder('Pilih karyawan untuk menghubungkan akun ini...')
                            ->relationship('employee', 'name')
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Employee $record) => "{$record->name} [NIK: " . ($record->custom_id ?: '-') . "] — " . ($record->role?->name ?? 'Sales'))
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $emp = \App\Models\Employee::find($state);
                                    if ($emp) {
                                        $set('name', $emp->name);
                                        if ($emp->custom_id) $set('custom_id', $emp->custom_id);
                                        if ($emp->homebase) $set('homebase', $emp->homebase);
                                        if ($emp->region) $set('region', $emp->region);
                                        if ($emp->email) $set('email', $emp->email);
                                        if ($emp->badan_usaha) $set('badan_usaha', $emp->badan_usaha);
                                        if ($emp->managed_branches) $set('managed_branches', $emp->getManagedBranchesList());
                                        if ($emp->role_id) $set('role_id', $emp->role_id);
                                    }
                                }
                            })
                            ->helperText('Pilih karyawan agar akun pengguna ini otomatis tersambung dengan profil dan data master karyawan.'),

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Pengguna')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Hendra Setia Permana')
                            ->helperText('Nama lengkap pengguna yang akan ditampilkan pada sistem.'),

                        Forms\Components\Select::make('role_id')
                            ->label('Role / Peran Pengguna')
                            ->relationship('role', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Role')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('Contoh: Supervisor (SPV) / Koordinator Operasional'),
                                Forms\Components\TextInput::make('code')
                                    ->label('Kode Role (Unik)')
                                    ->required()
                                    ->unique(table: 'roles', column: 'code')
                                    ->maxLength(50)
                                    ->placeholder('Contoh: SPV / KOR_OPS'),
                                Forms\Components\Textarea::make('description')
                                    ->label('Deskripsi / Tugas Role')
                                    ->rows(2)
                                    ->placeholder('Keterangan tanggung jawab dan alur approval...'),
                                Forms\Components\Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'Aktif' => 'Aktif',
                                        'Nonaktif' => 'Nonaktif',
                                    ])
                                    ->default('Aktif')
                                    ->required(),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                return \App\Models\Role::create($data)->getKey();
                            })
                            ->helperText('Menentukan hak akses approval dan plafon klaim operasional. Role yang belum terdaftar dapat langsung ditambahkan lewat tombol (+).'),

                        Forms\Components\Select::make('employee_id')
                            ->label('Tautkan ke Data Karyawan (Opsional)')
                            ->relationship('employee', 'name')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $emp = \App\Models\Employee::find($state);
                                    if ($emp) {
                                        if ($emp->custom_id) $set('custom_id', $emp->custom_id);
                                        if ($emp->name) $set('name', $emp->name);
                                        if ($emp->homebase) $set('homebase', $emp->homebase);
                                        if ($emp->region) $set('region', $emp->region);
                                        if ($emp->email) $set('email', $emp->email);
                                        if ($emp->badan_usaha) $set('badan_usaha', $emp->badan_usaha);
                                        if ($emp->managed_branches) $set('managed_branches', $emp->getManagedBranchesList());
                                        if ($emp->role_id) $set('role_id', $emp->role_id);
                                    }
                                }
                            })
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
                            ->helperText('Region operasional pengguna (untuk Sales, ASM, Finance, dll).')
                            ->options(self::getAvailableRegions())
                            ->searchable()
                            ->preload()
                            ->visible(function (Forms\Get $get, ?User $record) {
                                $roleId = $get('role_id') ?? $record?->role_id;
                                if (!$roleId && $record) return !$record->isAdmin() && !$record->isRgm();
                                if (!$roleId) return true;
                                $role = Role::find($roleId);
                                return !$role || !in_array(strtoupper($role->code ?? $role->name), ['ADMIN', 'SUPERADMIN', 'ADMINISTRATOR', 'RGM']);
                            }),

                        Forms\Components\Select::make('badan_usaha')
                            ->label('Badan Usaha Perusahaan')
                            ->options([
                                'PT MEDIA SELULAR INDONESIA' => 'PT MEDIA SELULAR INDONESIA',
                                'CV COMPLETE SELULAR' => 'CV COMPLETE SELULAR',
                                'CV TOP SELULAR' => 'CV TOP SELULAR',
                            ])
                            ->default('PT MEDIA SELULAR INDONESIA')
                            ->required(),

                        Forms\Components\Select::make('managed_branches')
                            ->label('Cabang Penugasan (Bisa Lebih Dari Satu)')
                            ->placeholder('Pilih satu atau beberapa cabang penugasan')
                            ->helperText('Role Sales, ASM, dan RGM dapat ditugaskan pada lebih dari 1 cabang.')
                            ->options(\App\Models\Branch::orderBy('name')->pluck('name', 'id')->toArray())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->default(fn (?User $record) => $record?->getManagedBranchesList() ?? [])
                            ->columnSpanFull()
                            ->visible(function (Forms\Get $get, ?User $record) {
                                $roleId = $get('role_id') ?? $record?->role_id;
                                if (!$roleId && $record) return $record->isSales() || $record->isAsm() || $record->isRgm() || $record->isAdmin();
                                if (!$roleId) return true;
                                $role = Role::find($roleId);
                                return $role && in_array(strtoupper($role->code ?? $role->name), ['SALES', 'SLS', 'DSF', 'ASC', 'ASM', 'RGM', 'ADMIN', 'SUPERADMIN']);
                            }),

                        Forms\Components\Select::make('managed_regions')
                            ->label('Region yang Dikelola (Bisa Lebih Dari Satu)')
                            ->placeholder('Pilih satu atau beberapa region yang dipegang Admin / RGM')
                            ->helperText('Role Admin dan RGM dapat mengelola lebih dari satu region (misal: CIREBON, JATENG, JABO). Klaim dari seluruh region yang dipilih akan masuk untuk diperiksa.')
                            ->options(self::getAvailableRegions())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->default(fn (?User $record) => $record?->getManagedRegionsList() ?? [])
                            ->columnSpanFull()
                            ->visible(function (Forms\Get $get, ?User $record) {
                                $roleId = $get('role_id') ?? $record?->role_id;
                                if (!$roleId && $record) return $record->isAdmin() || $record->isRgm();
                                if (!$roleId) return false;
                                $role = Role::find($roleId);
                                return $role && in_array(strtoupper($role->code ?? $role->name), ['ADMIN', 'SUPERADMIN', 'ADMINISTRATOR', 'RGM']);
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

                Forms\Components\Section::make('Autentikasi Login (ID Karyawan/NIK & PIN)')
                    ->description('Login sistem menggunakan ID Karyawan / NIK dan 6 Digit PIN Keamanan (atau Email & Password untuk Superadmin).')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('Email Kontak / Akun Superadmin')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('user@company.com')
                            ->helperText('Digunakan untuk notifikasi sistem (dan login akun Superadmin).'),

                        Forms\Components\TextInput::make('password')
                            ->label('Password (Khusus Superadmin)')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->helperText('Diisi jika ingin mengatur/mengubah password akun Superadmin.')
                            ->visible(function (Forms\Get $get, ?User $record) {
                                $roleId = $get('role_id') ?? $record?->role_id;
                                if (!$roleId && $record) return $record->isSuperAdmin();
                                if (!$roleId) return false;
                                $role = Role::find($roleId);
                                return $role && in_array(strtoupper($role->code ?? $role->name), ['SUPERADMIN', 'ADMINISTRATOR']);
                            }),

                        Forms\Components\TextInput::make('initial_pin')
                            ->label('PIN Awal (6 Digit Angka)')
                            ->numeric()
                            ->length(6)
                            ->default(fn () => str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT))
                            ->helperText('Diberikan otomatis oleh sistem untuk login pertama kali.')
                            ->visible(function (string $context, Forms\Get $get, ?User $record): bool {
                                if ($context !== 'create') return false;
                                $roleId = $get('role_id') ?? $record?->role_id;
                                if (!$roleId) return true;
                                $role = Role::find($roleId);
                                return !$role || !in_array(strtoupper($role->code ?? $role->name), ['SUPERADMIN', 'ADMINISTRATOR']);
                            }),

                        Forms\Components\Toggle::make('must_change_pin')
                            ->label('Wajib Buat PIN Baru saat Login')
                            ->default(true)
                            ->helperText('Jika aktif, pengguna wajib membuat 6 digit PIN baru saat login.')
                            ->visible(function (Forms\Get $get, ?User $record): bool {
                                $roleId = $get('role_id') ?? $record?->role_id;
                                if (!$roleId && $record) return !$record->isSuperAdmin();
                                if (!$roleId) return true;
                                $role = Role::find($roleId);
                                return !$role || !in_array(strtoupper($role->code ?? $role->name), ['SUPERADMIN', 'ADMINISTRATOR']);
                            }),
                    ])->columns(3),
            ]);
    }

    public static function getAvailableRegions(): array
    {
        $fromDb = \App\Models\Region::orderBy('name')->pluck('name', 'name')->toArray();
        if (!empty($fromDb)) {
            return $fromDb;
        }

        return [
            'CIREBON' => 'CIREBON',
            'JABO' => 'JABO',
            'JABODETABEK' => 'JABODETABEK',
            'JAKARTA' => 'JAKARTA',
            'JATENG' => 'JATENG',
            'JATIM' => 'JATIM',
            'JOGJA' => 'JOGJA',
            'PURWOKERTO' => 'PURWOKERTO',
            'SEMARANG' => 'SEMARANG',
            'SULAWESI' => 'SULAWESI',
            'SUMATERA' => 'SUMATERA',
            'SUMATERA BARAT' => 'SUMATERA BARAT',
            'SUMATRA UTARA & ACEH' => 'SUMATRA UTARA & ACEH',
            'LAMPUNG & BENGKULU' => 'LAMPUNG & BENGKULU',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['role', 'employee']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('custom_id')
                    ->label('ID Karyawan / NIK')
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
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Karyawan Terhubung')
                    ->badge()
                    ->color(fn (?User $record) => $record?->employee_id ? 'success' : 'gray')
                    ->state(fn (User $record) => $record->employee?->name ?? 'Akun Sistem / Mandiri')
                    ->description(fn (User $record) => $record->employee ? "NIK: " . ($record->employee->custom_id ?: '-') : null)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role.name')
                    ->label('Role')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('pin_status')
                    ->label('Status PIN')
                    ->badge()
                    ->state(fn (User $record) => $record->must_change_pin ? ("PIN Awal: " . ($record->initial_pin ?: 'Default') . " (Wajib Ubah)") : 'PIN Aktif (Sudah Diubah)')
                    ->color(fn (User $record) => $record->must_change_pin ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('region_display')
                    ->label('Region / Wilayah')
                    ->badge()
                    ->color('info')
                    ->state(function (User $record) {
                        if ($record->isAdmin() || $record->isRgm()) {
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
                Tables\Actions\Action::make('reset_pin')
                    ->label('Reset PIN')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record) => "Reset PIN: {$record->name}")
                    ->modalDescription('Sistem akan membuatkan 6 digit PIN baru dan mewajibkan pengguna mengganti PIN saat login berikutnya.')
                    ->action(function (User $record) {
                        $newPin = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                        $record->update([
                            'password' => \Illuminate\Support\Facades\Hash::make($newPin),
                            'initial_pin' => $newPin,
                            'must_change_pin' => true,
                        ]);

                        \Filament\Notifications\Notification::make()
                            ->title('PIN Berhasil Direset!')
                            ->body("PIN Baru untuk {$record->name} (ID: {$record->custom_id}) adalah: {$newPin}. Pengguna diwajibkan membuat PIN baru saat login.")
                            ->success()
                            ->persistent()
                            ->send();
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
