<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClaimPeriodResource\Pages;
use App\Filament\Resources\ClaimPeriodResource\RelationManagers\ClaimsRelationManager;
use App\Models\ClaimPeriod;
use App\Models\Employee;
use App\Services\ClaimExcelExportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClaimPeriodResource extends Resource
{
    protected static ?string $model = ClaimPeriod::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'KLAIM';

    protected static ?string $modelLabel = 'Periode & Rekap Klaim';

    protected static ?string $pluralModelLabel = 'Periode & Rekap Klaim';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user || $user->isFinance() || $user->isJejen()) return false;
        return $user->isSuperAdmin() || $user->isAdmin();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($user->isFinance()) return false;
        return true;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleToUser();
    }

    public static function form(Form $form): Form
    {
        $months = [
            'Januari' => 'Januari',
            'Februari' => 'Februari',
            'Maret' => 'Maret',
            'April' => 'April',
            'Mei' => 'Mei',
            'Juni' => 'Juni',
            'Juli' => 'Juli',
            'Agustus' => 'Agustus',
            'September' => 'September',
            'Oktober' => 'Oktober',
            'November' => 'November',
            'Desember' => 'Desember',
        ];

        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pengajuan Rekap')
                    ->description('Pilih karyawan, periode bulan, dan tanggal pengajuan rekap klaim')
                    ->schema([
                        Forms\Components\TextInput::make('period_number')
                            ->label('Nomor Pengajuan')
                            ->required()
                            ->default(fn () => 'CLM/' . date('Y') . '/' . date('m') . '/' . str_pad((string)(ClaimPeriod::count() + 1), 3, '0', STR_PAD_LEFT))
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('employee_id')
                            ->label('Pemohon / Karyawan')
                            ->relationship('employee', 'name')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                if ($state) {
                                    $emp = Employee::with('positionModel')->find($state);
                                    if ($emp && $emp->positionModel) {
                                        $budget = (float)$emp->positionModel->total_budget > 0 
                                            ? (float)$emp->positionModel->total_budget 
                                            : (float)$emp->positionModel->entertain_budget;
                                        $set('budget_claim', $budget);

                                        $total = (float) $get('total_claim');
                                        $already = (float) $get('already_claimed');
                                        $over = ($total + $already) > $budget && $budget > 0 ? ($total + $already) - $budget : 0;
                                        $set('over_budget', $over);
                                    }
                                }
                            })
                            ->required(),
                        Forms\Components\Select::make('month')
                            ->label('Bulan Periode')
                            ->options($months)
                            ->default(array_values($months)[(int)date('n') - 1])
                            ->required()
                            ->native(false),
                        Forms\Components\TextInput::make('year')
                            ->label('Tahun')
                            ->numeric()
                            ->default(date('Y'))
                            ->required(),
                        Forms\Components\DatePicker::make('submission_date')
                            ->label('Tanggal Pengajuan')
                            ->default(now())
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->label('Status Pengajuan')
                            ->options([
                                'DRAFT' => 'DRAFT',
                                'SELESAI' => 'SELESAI',
                                'DIAJUKAN' => 'DIAJUKAN',
                            ])
                            ->default('DRAFT')
                            ->required()
                            ->native(false),
                    ])->columns(3),

                Forms\Components\Section::make('Ringkasan Keuangan & Budget')
                    ->description('Perhitungan otomatis total klaim terhadap budget yang ditentukan')
                    ->schema([
                        Forms\Components\TextInput::make('total_claim')
                            ->label('Biaya Claim (Total Realisasi)')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated()
                            ->default(0)
                            ->helperText('Otomatis dihitung dari penjumlahan seluruh transaksi klaim pada periode ini.'),
                        Forms\Components\TextInput::make('budget_claim')
                            ->label('Budget Claim')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                $total = (float) $get('total_claim');
                                $already = (float) $get('already_claimed');
                                $budget = (float) $state;
                                $over = ($total + $already) > $budget && $budget > 0 ? ($total + $already) - $budget : 0;
                                $set('over_budget', $over);
                            }),
                        Forms\Components\TextInput::make('already_claimed')
                            ->label('Sudah Claim (Sebelumnya)')
                            ->numeric()
                            ->prefix('Rp')
                            ->default(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                $total = (float) $get('total_claim');
                                $already = (float) $state;
                                $budget = (float) $get('budget_claim');
                                $over = ($total + $already) > $budget && $budget > 0 ? ($total + $already) - $budget : 0;
                                $set('over_budget', $over);
                            }),
                        Forms\Components\TextInput::make('over_budget')
                            ->label('Over Budget')
                            ->numeric()
                            ->prefix('Rp')
                            ->disabled()
                            ->dehydrated()
                            ->default(0)
                            ->helperText('Otomatis bernilai selisih lebih jika (Total Klaim + Sudah Claim) melebihi Budget.'),
                    ])->columns(2),

                Forms\Components\Section::make('Catatan Tambahan')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Keterangan / Catatan Rekap')
                            ->rows(3)
                            ->placeholder('Tambahkan catatan penting terkait periode klaim ini...'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period_number')
                    ->label('No. Pengajuan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Pemohon')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ClaimPeriod $record) => $record->employee?->position ?? '-'),
                Tables\Columns\TextColumn::make('period_text')
                    ->label('Periode')
                    ->state(fn (ClaimPeriod $record) => "{$record->month} {$record->year}")
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('submission_date')
                    ->label('Tgl Pengajuan')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('claims_count')
                    ->label('Item')
                    ->counts('claims')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('total_claim')
                    ->label('Biaya Claim')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('budget_claim')
                    ->label('Budget')
                    ->money('IDR')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('over_budget')
                    ->label('Over Budget')
                    ->money('IDR')
                    ->color(fn ($state) => (float)$state > 0 ? 'danger' : 'gray')
                    ->weight(fn ($state) => (float)$state > 0 ? 'bold' : 'normal'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'SELESAI' => 'info',
                        'DIAJUKAN' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('month')
                    ->label('Bulan')
                    ->options([
                        'Januari' => 'Januari',
                        'Februari' => 'Februari',
                        'Maret' => 'Maret',
                        'April' => 'April',
                        'Mei' => 'Mei',
                        'Juni' => 'Juni',
                        'Juli' => 'Juli',
                        'Agustus' => 'Agustus',
                        'September' => 'September',
                        'Oktober' => 'Oktober',
                        'November' => 'November',
                        'Desember' => 'Desember',
                    ]),
                Tables\Filters\SelectFilter::make('year')
                    ->label('Tahun')
                    ->options(fn () => ClaimPeriod::query()->distinct()->pluck('year', 'year')->toArray()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'DRAFT' => 'DRAFT',
                        'SELESAI' => 'SELESAI',
                        'DIAJUKAN' => 'DIAJUKAN',
                    ]),
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Karyawan')
                    ->relationship('employee', 'name'),
            ])
            ->actions([
                Tables\Actions\Action::make('export_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(function (ClaimPeriod $record) {
                        return app(ClaimExcelExportService::class)->export($record);
                    }),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('set_status_selesai')
                        ->label('Set Status: SELESAI')
                        ->icon('heroicon-o-check-circle')
                        ->color('info')
                        ->visible(fn (ClaimPeriod $record) => $record->status === 'DRAFT')
                        ->action(function (ClaimPeriod $record) {
                            $record->update(['status' => 'SELESAI']);
                            Notification::make()->title('Status diubah ke SELESAI')->success()->send();
                        }),
                    Tables\Actions\Action::make('set_status_diajukan')
                        ->label('Set Status: DIAJUKAN')
                        ->icon('heroicon-o-paper-airplane')
                        ->color('success')
                        ->visible(fn (ClaimPeriod $record) => $record->status !== 'DIAJUKAN')
                        ->action(function (ClaimPeriod $record) {
                            $record->update(['status' => 'DIAJUKAN']);
                            Notification::make()->title('Status diubah ke DIAJUKAN')->success()->send();
                        }),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ClaimsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClaimPeriods::route('/'),
            'create' => Pages\CreateClaimPeriod::route('/create'),
            'edit' => Pages\EditClaimPeriod::route('/{record}/edit'),
            'view' => Pages\ViewClaimPeriod::route('/{record}'),
        ];
    }
}
