<?php

namespace App\Filament\Resources\ClaimResource\RelationManagers;

use App\Models\AttendanceDoc;
use App\Services\AttendanceWordExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AttendanceDocsRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceDoc';

    protected static ?string $title = 'Dokumen Daftar Hadir';

    protected static ?string $modelLabel = 'Daftar Hadir';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Kegiatan / Pertemuan')
                    ->description('Data kegiatan yang akan dicantumkan pada form resmi Daftar Hadir')
                    ->schema([
                        Forms\Components\TextInput::make('purpose')
                            ->label('Perihal / Acara')
                            ->required()
                            ->default(fn ($livewire) => $livewire->getOwnerRecord()->purpose)
                            ->maxLength(255),
                        Forms\Components\DatePicker::make('date')
                            ->label('Hari / Tanggal')
                            ->required()
                            ->default(fn ($livewire) => $livewire->getOwnerRecord()->claim_date ?? now()),
                        Forms\Components\TextInput::make('place')
                            ->label('Tempat Acara')
                            ->required()
                            ->default(fn ($livewire) => $livewire->getOwnerRecord()->note)
                            ->maxLength(255),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan / Agenda')
                            ->rows(2),
                    ])->columns(3),

                Forms\Components\Section::make('Peserta Hadir')
                    ->description('Daftar nama dan jabatan pihak yang hadir dalam pertemuan')
                    ->schema([
                        Forms\Components\Repeater::make('participants')
                            ->relationship('participants')
                            ->label('Daftar Peserta')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama Peserta')
                                    ->required()
                                    ->placeholder('Contoh: Hendra Setia Permana'),
                                Forms\Components\TextInput::make('position')
                                    ->label('Jabatan / Instansi')
                                    ->placeholder('Contoh: RGM / ASM Purwokerto / Dealer'),
                            ])
                            ->columns(2)
                            ->defaultItems(2)
                            ->addActionLabel('Tambah Peserta Hadir'),
                    ]),

                Forms\Components\Section::make('Dokumentasi Foto Pertemuan')
                    ->description('Upload foto dokumentasi kegiatan pertemuan (bisa lebih dari 2 foto). Foto akan otomatis disusun rapi dalam dokumen Word.')
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
                            ->helperText('Format JPG/PNG. Foto akan ditampilkan dalam format 1 halaman grid rapi pada lampiran Word.'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('purpose')
            ->columns([
                Tables\Columns\TextColumn::make('purpose')
                    ->label('Perihal / Kegiatan')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('place')
                    ->label('Tempat')
                    ->default('-'),
                Tables\Columns\TextColumn::make('participants_count')
                    ->label('Jumlah Peserta')
                    ->counts('participants')
                    ->badge()
                    ->color('info'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Buat Daftar Hadir')
                    ->visible(fn ($livewire) => !$livewire->getOwnerRecord()->attendanceDoc()->exists())
                    ->mutateFormDataUsing(function (array $data, $livewire): array {
                        $claim = $livewire->getOwnerRecord();
                        $data['claim_period_id'] = $claim->claim_period_id;
                        $data['user_id'] = $claim->user_id ?? auth()->id();
                        $data['employee_id'] = $claim->employee_id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('download_word')
                    ->label('Download Word (.docx)')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->action(function (AttendanceDoc $record) {
                        return app(AttendanceWordExportService::class)->export($record);
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
}
