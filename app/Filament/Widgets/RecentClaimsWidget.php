<?php

namespace App\Filament\Widgets;

use App\Models\Claim;
use App\Services\ClaimExcelExportService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentClaimsWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected static ?string $heading = 'Transaksi Pengajuan Klaim';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $filters = $this->filters;
        $selectedMonth = $filters['month'] ?? (int)now()->month;
        $selectedYear = (int)($filters['year'] ?? now()->year);
        $isAllMonth = ($selectedMonth === 'all');
        $m = $isAllMonth ? (int)now()->month : (int)$selectedMonth;
        $y = $selectedYear;

        $query = Claim::query()
            ->with(['employee.role', 'branch', 'user'])
            ->visibleToUser();

        if ($isAllMonth) {
            $query->where(function ($q) use ($y) {
                $q->where(function ($sub) use ($y) {
                    $sub->whereNotNull('claim_date')->whereYear('claim_date', $y);
                })->orWhere(function ($sub) use ($y) {
                    $sub->whereNull('claim_date')->whereYear('created_at', $y);
                });
            });
        } else {
            $query->where(function ($q) use ($m, $y) {
                $q->where(function ($sub) use ($m, $y) {
                    $sub->whereNotNull('claim_date')
                        ->whereMonth('claim_date', $m)
                        ->whereYear('claim_date', $y);
                })->orWhere(function ($sub) use ($m, $y) {
                    $sub->whereNull('claim_date')
                        ->whereMonth('created_at', $m)
                        ->whereYear('created_at', $y);
                });
            });
        }

        $query->latest('created_at');

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Tanggal Input')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('_uid')
                    ->label('_UID')
                    ->badge()
                    ->color('info')
                    ->weight('bold')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Pemohon')
                    ->weight('bold')
                    ->state(fn (Claim $record) => $record->effective_employee?->name ?? ($record->user?->name ?? '-'))
                    ->description(fn (Claim $record) => $record->effective_employee?->position_name ?? ($record->user?->getRoleLabel() ?? '-')),
                Tables\Columns\TextColumn::make('claim_type_string')
                    ->label('Jenis Biaya')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Entertain' => 'warning',
                        'BBM' => 'success',
                        'Perjalanan Dinas' => 'info',
                        'Service Motor' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('purpose_string')
                    ->label('Keperluan')
                    ->limit(35),
                Tables\Columns\TextColumn::make('city')
                    ->label('Kota / Region')
                    ->state(fn (Claim $record) => $record->city ?: ($record->region ?: '-'))
                    ->description(fn (Claim $record) => $record->region && $record->city ? "Region: {$record->region}" : null),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Nominal')
                    ->money('IDR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('approval_status')
                    ->label('Approval')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'DIAJUKAN' => 'warning',
                        'ACC_ASM' => 'info',
                        'ACC_RGM' => 'primary',
                        'ACC_PAK_JEJEN', 'DISETUJUI' => 'success',
                        'DITOLAK' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('disbursement_status')
                    ->label('Pencairan')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Sudah Dicairkan' => 'success',
                        'Belum Dicairkan' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('Periksa')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->url(fn (Claim $record) => match ($record->claim_category) {
                        'bbm' => \App\Filament\Resources\BbmClaimResource::getUrl('edit', ['record' => $record]),
                        'perdin' => \App\Filament\Resources\PerdinClaimResource::getUrl('edit', ['record' => $record]),
                        'service', 'service_motor' => \App\Filament\Resources\VehicleServiceClaimResource::getUrl('edit', ['record' => $record]),
                        default => \App\Filament\Resources\TransportEntertainClaimResource::getUrl('edit', ['record' => $record]),
                    })
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin()),
                \App\Filament\Actions\ViewClaimFilesAction::make(),
                Tables\Actions\Action::make('download_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(fn (Claim $record) => (bool)$record->is_perdin 
                        ? app(ClaimExcelExportService::class)->exportPerdinForm($record) 
                        : app(ClaimExcelExportService::class)->exportSingleClaim($record)),
            ]);
    }
}
