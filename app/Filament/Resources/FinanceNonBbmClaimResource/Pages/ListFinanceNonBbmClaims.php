<?php

namespace App\Filament\Resources\FinanceNonBbmClaimResource\Pages;

use App\Filament\Resources\FinanceNonBbmClaimResource;
use App\Models\Claim;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ListFinanceNonBbmClaims extends ListRecords
{
    protected static string $resource = FinanceNonBbmClaimResource::class;

    public function getDefaultActiveTab(): string | int | null
    {
        return 'menunggu';
    }

    public function getHeader(): ?View
    {
        return view('filament.components.finance-non-bbm-header');
    }

    public function getTabs(): array
    {
        $pendingCount = Claim::pendingFinanceNonBbmDisbursement()->count();
        $revisionCount = Claim::where('approval_status', 'DITOLAK_FINANCE')
            ->where(function (Builder $q) {
                $q->whereNull('claim_category')
                  ->orWhere('claim_category', '!=', 'bbm');
            })
            ->where(function (Builder $q) {
                $q->whereNull('claim_type')
                  ->orWhere('claim_type', 'not like', '%BBM%');
            })->count();

        return [
            'menunggu' => Tab::make('Menunggu Pencairan Langsung')
                ->badge($pendingCount > 0 ? (string) $pendingCount : null)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->pendingFinanceNonBbmDisbursement()),

            'revisi' => Tab::make('Perlu Revisi Admin')
                ->badge($revisionCount > 0 ? (string) $revisionCount : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('approval_status', 'DITOLAK_FINANCE')),

            'dicairkan' => Tab::make('Sudah Dicairkan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('disbursement_status', 'Sudah Dicairkan')),

            'semua' => Tab::make('Semua Klaim Operasional'),
        ];
    }
}
