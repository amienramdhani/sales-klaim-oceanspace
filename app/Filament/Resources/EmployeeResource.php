<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Role;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'MASTER DATA';

    protected static ?string $modelLabel = 'Karyawan';

    protected static ?string $pluralModelLabel = 'Data Karyawan';

    protected static ?int $navigationSort = 2;

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
        $isAsmOrRgm = function (Forms\Get $get, ?Employee $record): bool {
            $roleId = $get('role_id');
            if ($roleId) {
                $role = Role::find($roleId);
                if ($role) {
                    $code = strtoupper($role->code ?? '');
                    $name = strtoupper($role->name ?? '');
                    if (str_contains($code, 'ASM') || str_contains($code, 'RGM') || str_contains($name, 'ASM') || str_contains($name, 'RGM') || str_contains($name, 'AREA SALES') || str_contains($name, 'REGIONAL GENERAL')) {
                        return true;
                    }
                }
            }

            $posId = $get('position_id');
            if ($posId) {
                $pos = Position::find($posId);
                if ($pos) {
                    $name = strtoupper($pos->name ?? '');
                    if (str_contains($name, 'ASM') || str_contains($name, 'RGM') || str_contains($name, 'AREA SALES') || str_contains($name, 'REGIONAL GENERAL')) {
                        return true;
                    }
                }
            }

            if ($record) {
                return $record->isAsm() || $record->isRgm();
            }

            return false;
        };

        return $form
            ->schema([
                Forms\Components\Section::make('Akun Pengguna & ID User Terhubung')
                    ->description('Data akun login pengguna yang terhubung langsung dengan karyawan ini.')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Forms\Components\Placeholder::make('user_connection_info')
                            ->label('Status Akun Pengguna')
                            ->content(function (?Employee $record) {
                                if (!$record) {
                                    return 'Akun pengguna (User ID) dan 6 digit PIN awal akan otomatis dibuatkan dan terhubung saat data karyawan ini disimpan.';
                                }
                                $u = $record->user ?? \App\Models\User::where('employee_id', $record->id)->orWhere('custom_id', $record->custom_id)->first();
                                if (!$u) {
                                    return '⚠️ Belum memiliki akun user login. Akun akan dibuatkan otomatis saat Anda klik Simpan.';
                                }
                                $pinStatus = $u->must_change_pin ? 'PIN Awal: ' . ($u->initial_pin ?: '123456') . ' (Wajib ganti saat login)' : 'PIN Aktif (Sudah Diubah Mandiri)';
                                return "✅ Terhubung ke Akun User [ID Login: {$u->custom_id}] | Email: {$u->email} | Role: " . ($u->role?->name ?? 'User') . " | {$pinStatus}";
                            }),
                    ]),

                Forms\Components\Section::make('Informasi Karyawan')
                    ->description('Data diri karyawan yang berhak mengajukan klaim operasional')
                    ->schema([
                        Forms\Components\TextInput::make('custom_id')
                            ->label('ID Karyawan / NIK')
                            ->placeholder('Contoh: 2024.08.13.01 / EMP-001')
                            ->maxLength(50)
                            ->helperText('ID Karyawan atau NIK yang digunakan untuk login ke sistem.'),
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Hendra Setia Permana'),
                        Forms\Components\Select::make('role_id')
                            ->label('Role / Peran')
                            ->relationship('role', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $role = Role::find($state);
                                    if ($role) {
                                        $set('position', $role->name);
                                    }
                                }
                            }),
                        Forms\Components\Select::make('position_id')
                            ->label('Jabatan')
                            ->relationship('positionModel', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $pos = Position::find($state);
                                    if ($pos) {
                                        $set('position', $pos->name);
                                    }
                                }
                            }),
                        Forms\Components\Hidden::make('position')
                            ->default('Staff'),
                        Forms\Components\TextInput::make('homebase')
                            ->label('Kota Asal / Homebase')
                            ->default('Purwokerto')
                            ->required()
                            ->placeholder('Contoh: Purwokerto / Semarang'),
                        Forms\Components\Select::make('region_id')
                            ->label('Data Region')
                            ->relationship('regionModel', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Region')
                                    ->required()
                                    ->unique('regions', 'name'),
                                Forms\Components\TextInput::make('code')
                                    ->label('Kode Region')
                                    ->placeholder('Contoh: JTG'),
                            ])
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                if ($state) {
                                    $reg = \App\Models\Region::find($state);
                                    if ($reg) {
                                        $set('region', $reg->name);
                                    }
                                }
                            })
                            ->helperText('Pilih wilayah region operasional karyawan.'),
                        Forms\Components\Hidden::make('region'),
                        Forms\Components\TextInput::make('phone')
                            ->label('Nomor Telepon / WhatsApp')
                            ->tel()
                            ->maxLength(50)
                            ->placeholder('Contoh: 081234567890'),
                        Forms\Components\TextInput::make('email')
                            ->label('Alamat Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('Contoh: hendra@company.com'),
                        Forms\Components\Select::make('badan_usaha')
                            ->label('Badan Usaha')
                            ->options([
                                'PT MEDIA SELULAR INDONESIA' => 'PT MEDIA SELULAR INDONESIA',
                                'CV COMPLETE SELULAR' => 'CV COMPLETE SELULAR',
                                'CV TOP SELULAR' => 'CV TOP SELULAR',
                            ])
                            ->default('PT MEDIA SELULAR INDONESIA')
                            ->required(),

                        Forms\Components\Select::make('managed_branches')
                            ->label('Cabang Penugasan (Bisa Lebih Dari Satu)')
                            ->placeholder('Pilih satu atau beberapa cabang penugasan (Sales, ASM, RGM)')
                            ->helperText('Role Sales, ASM, dan RGM dapat ditugaskan pada lebih dari 1 cabang.')
                            ->options(\App\Models\Branch::orderBy('name')->pluck('name', 'id')->toArray())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->default(fn (?Employee $record) => $record?->getManagedBranchesList() ?? [])
                            ->columnSpanFull(),

                        Forms\Components\Select::make('status')
                            ->label('Status Karyawan')
                            ->options([
                                'Aktif' => 'Aktif',
                                'Nonaktif' => 'Nonaktif',
                            ])
                            ->default('Aktif')
                            ->required()
                            ->native(false),
                    ])->columns(2),

                Forms\Components\Section::make('Informasi Rekening Bank Pencairan')
                    ->description('Data rekening bank tujuan transfer pencairan klaim oleh tim Finance')
                    ->icon('heroicon-o-credit-card')
                    ->schema([
                        Forms\Components\TextInput::make('bank_name')
                            ->label('Nama Bank')
                            ->placeholder('Contoh: BCA / MANDIRI / BRI / BNI / BSI')
                            ->datalist([
                                'BCA', 'MANDIRI', 'BRI', 'BNI', 'BSI', 'CIMB NIAGA', 'PERMATA', 'DANAMON', 'BTN', 'JAGO', 'SEABANK', 'BCA SYARIAH'
                            ])
                            ->maxLength(100),

                        Forms\Components\TextInput::make('bank_account_number')
                            ->label('Nomor Rekening')
                            ->placeholder('Contoh: 1234567890')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('bank_account_name')
                            ->label('Nama Pemilik Rekening (Atas Nama)')
                            ->placeholder('Contoh: HENDRA SETIA PERMANA')
                            ->maxLength(255)
                            ->helperText('Nama yang tertera pada buku tabungan / rekening.'),
                    ])->columns(3),

                Forms\Components\Section::make('Alokasi Plafon & Budget Karyawan')
                    ->description('Tentukan anggaran operasional per bulan untuk karyawan ini. Plafon BBM dan Entertain khusus untuk jabatan ASM dan RGM.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Forms\Components\TextInput::make('bbm_budget')
                            ->label('Budget BBM Bulanan (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->visible($isAsmOrRgm)
                            ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (float)$state : 0)
                            ->helperText('Dana BBM bulanan yang dicairkan Finance untuk ASM/RGM ini. Wajib diisi agar Finance dapat mencairkan dana.'),

                        Forms\Components\TextInput::make('entertain_budget')
                            ->label('Custom Budget Entertain (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->placeholder('Standar: ASM 1.5jt / RGM 2.4jt')
                            ->visible($isAsmOrRgm)
                            ->helperText('Plafon biaya entertain bulanan khusus ASM dan RGM (ASM: Rp 1.500.000, RGM: Rp 2.400.000).'),

                        Forms\Components\TextInput::make('perdin_budget')
                            ->label('Budget Perjalanan Dinas (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (float)$state : 0)
                            ->helperText('Batas tunjangan & akomodasi perjalanan dinas bulanan.'),

                        Forms\Components\TextInput::make('transport_budget')
                            ->label('Budget Transport / Tol / Parkir')
                            ->placeholder('Tanpa Limit (Bebas Biaya)')
                            ->disabled()
                            ->dehydrated()
                            ->default(0)
                            ->dehydrateStateUsing(fn () => 0)
                            ->helperText('SOP Baru: Uang tol, parkir, dan tiket transportasi TIDAK MEMILIKI BATASAN LIMIT.'),
                    ])->columns(2),

                Forms\Components\Section::make('Standar Anggaran Perjalanan Dinas (Perdin Daerah)')
                    ->description('Sesuaikan standar uang makan, akomodasi hotel, dan transport untuk karyawan ini sesuai penugasan daerah masing-masing.')
                    ->icon('heroicon-o-briefcase')
                    ->schema([
                        Forms\Components\TextInput::make('perdin_meal_allowance')
                            ->label('Uang Makan Perdin / Hari (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(100000)
                            ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (float)$state : 100000)
                            ->helperText('Contoh: 100.000 (RGM), 75.000 (ASM), 50.000 (Sales) atau sesuai standar daerah'),

                        Forms\Components\TextInput::make('perdin_lodging_allowance')
                            ->label('Uang Penginapan Hotel / Malam (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(250000)
                            ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (float)$state : 250000)
                            ->helperText('Contoh: 300.000 (RGM), 250.000 (ASM) atau sesuai tarif hotel daerah'),

                        Forms\Components\TextInput::make('perdin_transport_budget')
                            ->label('Standar Tiket / Transport Perdin (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(500000)
                            ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (float)$state : 500000)
                            ->helperText('Standar pagu tiket transportasi / sewa kendaraan per daerah'),
                    ])->columns(3),

                Forms\Components\Section::make('Hierarki Organisasi & Atasan Langsung')
                    ->description('Tentukan atasan langsung karyawan ini. RGM menjadi atasan ASM, ASM menjadi atasan Sales. Digunakan untuk pelacakan klaim dan rekapan per tim.')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Forms\Components\Select::make('supervisor_id')
                            ->label('Atasan Langsung (Hierarki Persetujuan)')
                            ->relationship('supervisor', 'name', modifyQueryUsing: fn (Builder $query, ?Employee $record) => $record ? $query->where('id', '!=', $record->id) : $query)
                            ->getOptionLabelFromRecordUsing(fn (\App\Models\Employee $record) => "{$record->name} — [{$record->position_name}]" . ($record->region ? " ({$record->region})" : ''))
                            ->searchable()
                            ->preload()
                            ->placeholder('Pilih Atasan Langsung...')
                            ->helperText('Hierarki: Sales memilih ASM sebagai atasan, ASM memilih RGM sebagai atasan, RGM memilih Management.')
                            ->nullable(),
                    ]),

                Forms\Components\Section::make('Tanda Tangan Digital Karyawan')
                    ->description('Upload file tanda tangan resmi karyawan (Format PNG transparan / JPG). Tanda tangan ini otomatis disematkan pada dokumen Daftar Hadir Meeting saat nama karyawan ini tercatat hadir.')
                    ->schema([
                        Forms\Components\FileUpload::make('signature_image')
                            ->label('File Tanda Tangan')
                            ->image()
                            ->disk('public')
                            ->directory('signatures')
                            ->visibility('public')
                            ->openable()
                            ->downloadable()
                            ->helperText('Gunakan gambar tanda tangan PNG dengan latar belakang transparan.'),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['role', 'positionModel', 'supervisor', 'regionModel', 'user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('custom_id')
                    ->label('ID')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default('-'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user_account')
                    ->label('ID Pengguna & User')
                    ->state(function (Employee $record) {
                        $u = $record->user ?? \App\Models\User::where('employee_id', $record->id)->orWhere('custom_id', $record->custom_id)->first();
                        if (!$u) {
                            return 'Belum Terhubung';
                        }
                        return $u->custom_id ?: $u->name;
                    })
                    ->description(function (Employee $record) {
                        $u = $record->user ?? \App\Models\User::where('employee_id', $record->id)->orWhere('custom_id', $record->custom_id)->first();
                        if (!$u) {
                            return 'Klik Kelola User';
                        }
                        return ($u->role?->name ?? 'Role User') . ($u->initial_pin && $u->must_change_pin ? " [PIN: {$u->initial_pin}]" : '');
                    })
                    ->badge()
                    ->color(function (Employee $record) {
                        $u = $record->user ?? \App\Models\User::where('employee_id', $record->id)->orWhere('custom_id', $record->custom_id)->first();
                        return $u ? 'success' : 'danger';
                    })
                    ->icon(function (Employee $record) {
                        $u = $record->user ?? \App\Models\User::where('employee_id', $record->id)->orWhere('custom_id', $record->custom_id)->first();
                        return $u ? 'heroicon-m-check-badge' : 'heroicon-m-x-circle';
                    }),
                Tables\Columns\TextColumn::make('position_display')
                    ->label('Jabatan / Role')
                    ->state(fn (Employee $record) => $record->role?->name ?? $record->positionModel?->name ?? $record->position ?? '-')
                    ->searchable(query: fn ($query, $search) => $query->where('position', 'like', "%{$search}%")->orWhereHas('role', fn ($q) => $q->where('name', 'like', "%{$search}%")))
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('region')
                    ->label('Region')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->default('-'),
                Tables\Columns\TextColumn::make('supervisor.name')
                    ->label('Atasan Langsung')
                    ->description(fn (Employee $record) => $record->supervisor?->position_name ?? null)
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bbm_budget')
                    ->label('Budget BBM')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                Tables\Columns\TextColumn::make('entertain_budget')
                    ->label('Budget Entertain')
                    ->money('IDR')
                    ->state(fn (Employee $record) => $record->entertain_budget)
                    ->sortable()
                    ->color('success'),
                Tables\Columns\TextColumn::make('perdin_meal_allowance')
                    ->label('Uang Makan Perdin')
                    ->money('IDR')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('perdin_lodging_allowance')
                    ->label('Hotel Perdin')
                    ->money('IDR')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('perdin_transport_budget')
                    ->label('Transport Perdin')
                    ->money('IDR')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('homebase')
                    ->label('Homebase')
                    ->searchable()
                    ->default('Purwokerto'),
                Tables\Columns\TextColumn::make('bank_account_number')
                    ->label('Rekening Bank')
                    ->description(fn (Employee $record) => $record->bank_name ? "{$record->bank_name} a/n {$record->bank_account_name}" : null)
                    ->placeholder('Belum Ada')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Aktif' => 'success',
                        'Nonaktif' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role_id')
                    ->label('Role')
                    ->relationship('role', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Aktif' => 'Aktif',
                        'Nonaktif' => 'Nonaktif',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('sync_user_account')
                    ->label('Kelola User ID')
                    ->icon('heroicon-o-key')
                    ->color('info')
                    ->modalHeading(fn (Employee $record) => "Kelola Akun Login & User: {$record->name}")
                    ->modalDescription('ID Pengguna / NIK dan PIN digunakan karyawan untuk login ke sistem.')
                    ->modalSubmitActionLabel('Simpan & Sinkronkan User')
                    ->form([
                        Forms\Components\Placeholder::make('info_karyawan')
                            ->label('Data Karyawan')
                            ->content(fn (Employee $record) => "Nama: {$record->name} | NIK: " . ($record->custom_id ?: 'Belum diisi') . " | Role: " . ($record->role?->name ?? 'Sales')),
                        Forms\Components\TextInput::make('user_custom_id')
                            ->label('ID Karyawan / NIK (Untuk Login)')
                            ->default(fn (Employee $record) => $record->user?->custom_id ?? $record->custom_id)
                            ->required(),
                        Forms\Components\TextInput::make('user_email')
                            ->label('Email Akun')
                            ->email()
                            ->default(fn (Employee $record) => $record->user?->email ?? ($record->email ?: \Illuminate\Support\Str::slug($record->name, '.') . '@msi.co.id'))
                            ->required(),
                        Forms\Components\Select::make('user_role_id')
                            ->label('Role Akses Pengguna')
                            ->relationship('role', 'name')
                            ->default(fn (Employee $record) => $record->user?->role_id ?? $record->role_id)
                            ->required(),
                        Forms\Components\TextInput::make('new_pin')
                            ->label('Reset / Ganti PIN (6 Digit Angka)')
                            ->numeric()
                            ->length(6)
                            ->placeholder('Kosongkan jika tidak ingin mengubah PIN')
                            ->helperText('Jika diisi, PIN login akun pengguna ini akan diubah dan wajib diperbarui saat login pertama.'),
                    ])
                    ->action(function (Employee $record, array $data) {
                        $user = $record->user ?? \App\Models\User::where('employee_id', $record->id)->orWhere('custom_id', $record->custom_id)->first();
                        $nik = trim($data['user_custom_id']);
                        $email = trim($data['user_email']);
                        $roleId = $data['user_role_id'];
                        $newPin = !empty($data['new_pin']) ? trim($data['new_pin']) : null;

                        if (!$user) {
                            $pin = $newPin ?: str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                            $user = \App\Models\User::create([
                                'name' => $record->name,
                                'custom_id' => $nik,
                                'email' => $email,
                                'password' => \Illuminate\Support\Facades\Hash::make($pin),
                                'must_change_pin' => true,
                                'initial_pin' => $pin,
                                'employee_id' => $record->id,
                                'role_id' => $roleId,
                                'homebase' => $record->homebase,
                                'region' => $record->region,
                                'managed_branches' => $record->getManagedBranchesList(),
                                'company' => $record->company,
                                'badan_usaha' => $record->badan_usaha,
                            ]);
                            \Filament\Notifications\Notification::make()
                                ->title('Akun Pengguna Berhasil Dibuat')
                                ->body("User ID {$nik} telah dibuat dengan PIN awal: {$pin}")
                                ->success()
                                ->send();
                        } else {
                            $user->employee_id = $record->id;
                            $user->name = $record->name;
                            $user->custom_id = $nik;
                            $user->email = $email;
                            $user->role_id = $roleId;
                            $user->region = $record->region;
                            $user->homebase = $record->homebase;
                            if ($newPin) {
                                $user->password = \Illuminate\Support\Facades\Hash::make($newPin);
                                $user->initial_pin = $newPin;
                                $user->must_change_pin = true;
                                $user->pin_changed_at = null;
                            }
                            $user->save();
                            \Filament\Notifications\Notification::make()
                                ->title('Akun Pengguna Berhasil Disinkronkan')
                                ->body("Data user ID {$nik} telah diperbarui" . ($newPin ? " dengan PIN baru: {$newPin}" : ''))
                                ->success()
                                ->send();
                        }

                        if ($record->custom_id !== $nik || $record->email !== $email || $record->role_id !== $roleId) {
                            $record->custom_id = $nik;
                            $record->email = $email;
                            $record->role_id = $roleId;
                            $record->saveQuietly();
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
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
