<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceDocResource\Pages;
use App\Models\AttendanceDoc;
use App\Models\Employee;
use App\Services\AttendanceWordExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceDocResource extends Resource
{
    protected static ?string $model = AttendanceDoc::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'DOKUMEN';

    protected static ?string $modelLabel = 'Daftar Hadir';

    protected static ?string $pluralModelLabel = 'Daftar Hadir Meeting';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && ($user->isAdmin() || $user->isSuperAdmin());
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user && !$user->isSuperAdmin()) {
            $query->where(function ($q) use ($user) {
                $q->where('user_id', $user->id);
                $empId = $user->getEffectiveEmployeeId();
                if ($empId) {
                    $q->orWhere('employee_id', $empId);
                }
            });
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Kegiatan / Meeting')
                    ->description('Isi data agenda pertemuan untuk pembuatan dokumen resmi Daftar Hadir Meeting')
                    ->schema([
                        // 1. PERIHAL
                        Forms\Components\TextInput::make('purpose')
                            ->label('PERIHAL')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: MEETING KOORDINASI ASM & TEAM LEADER')
                            ->columnSpan(2),

                        // 2. TGL
                        Forms\Components\DatePicker::make('date')
                            ->label('TANGGAL')
                            ->default(now())
                            ->required(),

                        // 3. TEMPAT
                        Forms\Components\TextInput::make('place')
                            ->label('TEMPAT')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: WARUNG MAKAN SUMBER REJEKI SURABAYA')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Agenda (Opsional)')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(3),

                Forms\Components\Section::make('Daftar Peserta Hadir')
                    ->description('Tambahkan nama peserta pertemuan secara bebas (tidak wajib terdaftar sebagai karyawan). Jika nama peserta cocok dengan data karyawan yang memiliki tanda tangan resmi, tanda tangan akan otomatis disematkan.')
                    ->schema([
                        Forms\Components\Repeater::make('participants')
                            ->relationship('participants')
                            ->label('Daftar Peserta')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Peserta')
                                    ->required()
                                    ->placeholder('Ketik nama peserta bebas (Contoh: Hendra Setia Permana / Dealer Purwokerto)')
                                    ->datalist(fn () => Employee::pluck('name')->toArray())
                                    ->columnSpanFull(),
                            ])
                            ->defaultItems(2)
                            ->addActionLabel('Tambah Peserta Hadir'),
                    ]),

                Forms\Components\Section::make('Dokumentasi Foto Pertemuan')
                    ->description('Upload foto dokumentasi pertemuan untuk dilampirkan dalam dokumen Word')
                    ->schema([
                        Forms\Components\FileUpload::make('photos')
                            ->label('Foto Dokumentasi')
                            ->image()
                            ->multiple()
                            ->disk('public')
                            ->directory('attendance-photos')
                            ->visibility('public')
                            ->reorderable()
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull()
                            ->helperText('Format JPG/PNG. Foto akan dilampirkan dengan format proporsional dan mudah diatur In Front of Text.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('purpose')
                    ->label('PERIHAL')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->limit(40),
                Tables\Columns\TextColumn::make('date')
                    ->label('TANGGAL')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('place')
                    ->label('TEMPAT')
                    ->searchable()
                    ->default('-'),
                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Peserta')
                    ->counts('participants')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Dibuat Oleh')
                    ->placeholder('-')
                    ->visible(fn () => auth()->user()?->isSuperAdmin() ?? false),
            ])
            ->defaultSort('date', 'desc')
            ->actions([
                Tables\Actions\Action::make('download_word')
                    ->label('Download Word')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->action(fn (AttendanceDoc $record) => app(AttendanceWordExportService::class)->export($record)),
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
            'index' => Pages\ListAttendanceDocs::route('/'),
            'create' => Pages\CreateAttendanceDoc::route('/create'),
            'edit' => Pages\EditAttendanceDoc::route('/{record}/edit'),
        ];
    }
}
