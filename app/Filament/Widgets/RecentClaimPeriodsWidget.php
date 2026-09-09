<?php

namespace App\Filament\Widgets;

use App\Models\ClaimPeriod;
use App\Services\ClaimExcelExportService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentClaimPeriodsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Rekap Pengajuan Klaim Terbaru';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ClaimPeriod::query()->latest('submission_date')->limit(6)
            )
            ->columns([
                Tables\Columns\TextColumn::make('period_number')
                    ->label('No. Pengajuan')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('Pemohon')
                    ->description(fn (ClaimPeriod $record) => $record->employee?->position ?? '-'),
                Tables\Columns\TextColumn::make('period')
                    ->label('Periode')
                    ->state(fn (ClaimPeriod $record) => "{$record->month} {$record->year}"),
                Tables\Columns\TextColumn::make('submission_date')
                    ->label('Tgl Pengajuan')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('total_claim')
                    ->label('Total Klaim')
                    ->money('IDR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('budget_claim')
                    ->label('Budget')
                    ->money('IDR'),
                Tables\Columns\TextColumn::make('over_budget')
                    ->label('Over Budget')
                    ->money('IDR')
                    ->color(fn ($state) => (float)$state > 0 ? 'danger' : 'gray'),
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
            ->actions([
                Tables\Actions\Action::make('export_excel')
                    ->label('Download Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                    ->action(fn (ClaimPeriod $record) => app(ClaimExcelExportService::class)->export($record)),
            ]);
    }
}
