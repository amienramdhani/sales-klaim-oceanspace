<?php

namespace App\Filament\Resources\FinanceBbmClaimResource\Pages;

use App\Filament\Resources\FinanceBbmClaimResource;
use App\Models\Claim;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class ListFinanceBbmClaims extends ListRecords
{
    protected static string $resource = FinanceBbmClaimResource::class;

    public function getDefaultActiveTab(): string | int | null
    {
        return 'menunggu';
    }

    public function getHeader(): ?View
    {
        return view('filament.components.finance-bbm-header');
    }

    public function getTabs(): array
    {
        $pendingCount = Claim::pendingFinanceBbmDisbursement()->count();
        $revisionCount = Claim::where('approval_status', 'DITOLAK_FINANCE')
            ->where(function (Builder $q) {
                $q->where('claim_category', 'bbm')
                  ->orWhere('claim_type', 'like', '%BBM%');
            })->count();

        return [
            'menunggu' => Tab::make('Menunggu Pencairan (Nota Balik)')
                ->badge($pendingCount > 0 ? (string) $pendingCount : null)
                ->badgeColor(now()->day > 10 ? 'danger' : 'warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->pendingFinanceBbmDisbursement()),

            'revisi' => Tab::make('Perlu Revisi Admin')
                ->badge($revisionCount > 0 ? (string) $revisionCount : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('approval_status', 'DITOLAK_FINANCE')),

            'dicairkan' => Tab::make('Sudah Dicairkan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('disbursement_status', 'Sudah Dicairkan')),

            'semua' => Tab::make('Semua Klaim BBM'),
        ];
    }
}
