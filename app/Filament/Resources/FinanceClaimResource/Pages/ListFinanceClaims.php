<?php

namespace App\Filament\Resources\FinanceClaimResource\Pages;

use App\Filament\Resources\FinanceClaimResource;
use App\Models\Claim;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListFinanceClaims extends ListRecords
{
    protected static string $resource = FinanceClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        $baseQuery = fn () => Claim::query()->where(function ($q) {
            $q->where('claim_category', 'bbm')
              ->orWhere('claim_category', 'perdin')
              ->orWhere('is_perdin', true)
              ->orWhere('nota_balik_submitted', true);
        })->where(function ($q) {
            $q->where('approval_status', '!=', 'DRAFT')
              ->orWhere('disbursement_status', 'Sudah Dicairkan');
        });

        return [
            'all' => Tab::make('Semua Nota Balik')
                ->icon('heroicon-o-queue-list')
                ->badge(fn () => $baseQuery()->count()),

            'waiting_transfer' => Tab::make('Belum Dicairkan')
                ->icon('heroicon-o-clock')
                ->badge(fn () => $baseQuery()
                    ->where('disbursement_status', 'Belum Dicairkan')
                    ->where('approval_status', '!=', 'DITOLAK')
                    ->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('disbursement_status', 'Belum Dicairkan')
                    ->where('approval_status', '!=', 'DITOLAK')),

            'disbursed' => Tab::make('Sudah Dicairkan')
                ->icon('heroicon-o-check-circle')
                ->badge(fn () => $baseQuery()
                    ->where('disbursement_status', 'Sudah Dicairkan')
                    ->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('disbursement_status', 'Sudah Dicairkan')),

            'rejected' => Tab::make('Ditolak')
                ->icon('heroicon-o-x-circle')
                ->badge(fn () => $baseQuery()
                    ->where('approval_status', 'DITOLAK')
                    ->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('approval_status', 'DITOLAK')),
        ];
    }
}
