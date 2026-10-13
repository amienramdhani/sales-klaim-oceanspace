<?php

namespace App\Filament\Resources\NurbaitiClaimApprovalResource\Pages;

use App\Filament\Resources\NurbaitiClaimApprovalResource;
use App\Models\Claim;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListNurbaitiClaimApprovals extends ListRecords
{
    protected static string $resource = NurbaitiClaimApprovalResource::class;

    public function getDefaultActiveTab(): string | int | null
    {
        return 'menunggu';
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $pendingCount = Claim::pendingNurbaitiApproval()->count();

        return [
            'menunggu' => Tab::make('Menunggu Persetujuan')
                ->badge($pendingCount > 0 ? (string) $pendingCount : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->pendingNurbaitiApproval()),

            'disetujui' => Tab::make('Sudah Saya ACC')
                ->modifyQueryUsing(function (Builder $query) use ($user) {
                    if ($user && !$user->isSuperAdmin()) {
                        return $query->where('approved_by_nurbaiti_id', $user->id);
                    }
                    return $query->whereNotNull('approved_by_nurbaiti_id');
                }),

            'ditolak' => Tab::make('Ditolak')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('approval_status', 'DITOLAK')),

            'semua' => Tab::make('Semua Data'),
        ];
    }
}
