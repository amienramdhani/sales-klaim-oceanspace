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
        $user = auth()->user();
        $bu = $user?->badan_usaha;
        $applyBu = function (Builder $q) use ($bu) {
            if (!empty($bu)) {
                $upper = strtoupper(trim($bu));
                $q->where(function (Builder $sub) use ($upper) {
                    $sub->whereRaw('UPPER(badan_usaha) LIKE ?', ["%{$upper}%"])
                        ->orWhereRaw('UPPER(company) LIKE ?', ["%{$upper}%"]);
                });
            }
        };

        $pendingCount = Claim::pendingFinanceBbmDisbursement()->where($applyBu)->count();
        $waitingAtasanCount = Claim::approvedByAdminWaitingExecutive()
            ->where(function (Builder $q) {
                $q->where('claim_category', 'bbm')
                  ->orWhere('claim_type', 'like', '%BBM%');
            })
            ->where($applyBu)
            ->count();

        $revisionCount = Claim::where('approval_status', 'DITOLAK_FINANCE')
            ->where(function (Builder $q) {
                $q->where('claim_category', 'bbm')
                  ->orWhere('claim_type', 'like', '%BBM%');
            })
            ->where($applyBu)
            ->count();

        return [
            'menunggu' => Tab::make('Siap Dicairkan (Nota Balik)')
                ->badge($pendingCount > 0 ? (string) $pendingCount : null)
                ->badgeColor(now()->day > 10 ? 'danger' : 'warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->pendingFinanceBbmDisbursement()),

            'menunggu_atasan' => Tab::make('Proses Approval Atasan')
                ->badge($waitingAtasanCount > 0 ? (string) $waitingAtasanCount : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->approvedByAdminWaitingExecutive()),

            'revisi' => Tab::make('Perlu Revisi Admin')
                ->badge($revisionCount > 0 ? (string) $revisionCount : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('approval_status', 'DITOLAK_FINANCE')),

            'dicairkan' => Tab::make('Sudah Dicairkan')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('disbursement_status', 'Sudah Dicairkan')),

            'semua' => Tab::make('Semua Nota Balik BBM'),
        ];
    }
}
