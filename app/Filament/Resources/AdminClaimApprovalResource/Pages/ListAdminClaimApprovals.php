<?php

namespace App\Filament\Resources\AdminClaimApprovalResource\Pages;

use App\Filament\Resources\AdminClaimApprovalResource;
use App\Models\Claim;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAdminClaimApprovals extends ListRecords
{
    protected static string $resource = AdminClaimApprovalResource::class;

    public function getDefaultActiveTab(): string | int | null
    {
        return 'menunggu';
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $pendingCount = $user ? Claim::pendingAdminVerification($user)->count() : 0;
        $revisionCount = $user ? (clone Claim::pendingAdminVerification($user))->where('approval_status', 'DITOLAK_FINANCE')->count() : 0;

        return [
            'menunggu' => Tab::make('Menunggu Verifikasi')
                ->badge($pendingCount > 0 ? (string) $pendingCount : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->pendingAdminVerification($user)),

            'revisi_finance' => Tab::make('Perlu Revisi Finance')
                ->badge($revisionCount > 0 ? (string) $revisionCount : null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('approval_status', 'DITOLAK_FINANCE')),

            'disetujui' => Tab::make('Sudah Diverifikasi')
                ->modifyQueryUsing(function (Builder $query) use ($user) {
                    if ($user && !$user->isSuperAdmin()) {
                        return $query->where('admin_approved_by_id', $user->id);
                    }
                    return $query->whereNotNull('admin_approved_by_id');
                }),

            'semua' => Tab::make('Semua Data'),
        ];
    }
}
