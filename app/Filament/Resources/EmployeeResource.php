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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Karyawan')
                    ->description('Data diri karyawan yang berhak mengajukan klaim operasional')
                    ->schema([
                        Forms\Components\TextInput::make('custom_id')
                            ->label('ID Karyawan')
                            ->placeholder('Contoh: EMP-001 / RGM-PWT01')
                            ->maxLength(50),
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
                        Forms\Components\TextInput::make('region')
                            ->label('Data Region')
                            ->placeholder('Contoh: CIREBON / JABO / JATENG')
                            ->datalist([
                                'CIREBON', 'JABO', 'JABODETABEK', 'JATENG', 'JATIM',
                                'JOGJA', 'SUMATERA', 'SUMATERA BARAT', 'SUMATRA UTARA & ACEH',
                                'SULAWESI', 'LAMPUNG & BENGKULU', 'PURWOKERTO', 'JAKARTA'
                            ]),
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

                Forms\Components\Section::make('Alokasi Plafon & Budget Karyawan')
                    ->description('Tentukan anggaran operasional per bulan untuk karyawan ini. Budget BBM diatur fleksibel per user dan terintegrasi langsung ke alur pencairan Finance.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Forms\Components\TextInput::make('bbm_budget')
                            ->label('Budget BBM Bulanan (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (float)$state : 0)
                            ->helperText('Dana BBM bulanan yang dicairkan Finance untuk ASM/RGM ini. Wajib diisi agar Finance dapat mencairkan dana.'),

                        Forms\Components\TextInput::make('entertain_budget')
                            ->label('Custom Budget Entertain (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->placeholder('Standar: ASM 1.5jt / RGM 2.4jt')
                            ->helperText('Kosongkan jika menggunakan standar jabatan (ASM: Rp 1.500.000, RGM: Rp 2.400.000).'),

                        Forms\Components\TextInput::make('perdin_budget')
                            ->label('Budget Perjalanan Dinas (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (float)$state : 0)
                            ->helperText('Batas tunjangan & akomodasi perjalanan dinas bulanan.'),

                        Forms\Components\TextInput::make('transport_budget')
                            ->label('Budget Transport / Tol / Parkir (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->dehydrateStateUsing(fn ($state) => $state !== null && $state !== '' ? (float)$state : 0)
                            ->helperText('Batas kompensasi tiket transportasi, tol, dan parkir.'),
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
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Aktif' => 'success',
                        'Nonaktif' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('supervisor.name')
                    ->label('Atasan Langsung')
                    ->default('-')
                    ->searchable()
                    ->badge()
                    ->color('warning'),
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
