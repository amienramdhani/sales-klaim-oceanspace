<?php

namespace App\Filament\Resources\RgmClaimApprovalResource\Pages;

use App\Filament\Resources\RgmClaimApprovalResource;
use App\Models\Claim;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListRgmClaimApprovals extends ListRecords
{
    protected static string $resource = RgmClaimApprovalResource::class;

    public function getDefaultActiveTab(): string | int | null
    {
        return 'menunggu';
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $pendingCount = $user ? Claim::pendingRgmApproval($user)->count() : 0;

        return [
            'menunggu' => Tab::make('Menunggu Persetujuan')
                ->badge($pendingCount > 0 ? (string) $pendingCount : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->pendingRgmApproval($user)),

            'disetujui' => Tab::make('Sudah Saya ACC')
                ->modifyQueryUsing(function (Builder $query) use ($user) {
                    if ($user && !$user->isSuperAdmin()) {
                        return $query->where('approved_by_rgm_id', $user->id);
                    }
                    return $query->whereNotNull('approved_by_rgm_id');
                }),

            'ditolak' => Tab::make('Ditolak')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('approval_status', 'DITOLAK')),

            'semua' => Tab::make('Semua Data Tim'),
        ];
    }
}
