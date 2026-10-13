<?php

namespace App\Filament\Resources\YogaClaimApprovalResource\Pages;

use App\Filament\Resources\YogaClaimApprovalResource;
use App\Models\Claim;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListYogaClaimApprovals extends ListRecords
{
    protected static string $resource = YogaClaimApprovalResource::class;

    public function getDefaultActiveTab(): string | int | null
    {
        return 'menunggu';
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $pendingCount = Claim::pendingYogaApproval()->count();

        return [
            'menunggu' => Tab::make('Menunggu Persetujuan')
                ->badge($pendingCount > 0 ? (string) $pendingCount : null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->pendingYogaApproval()),

            'disetujui' => Tab::make('Sudah Saya ACC')
                ->modifyQueryUsing(function (Builder $query) use ($user) {
                    if ($user && !$user->isSuperAdmin()) {
                        return $query->where('approved_by_yoga_id', $user->id);
                    }
                    return $query->whereNotNull('approved_by_yoga_id');
                }),

            'ditolak' => Tab::make('Ditolak')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('approval_status', 'DITOLAK')),

            'semua' => Tab::make('Semua Data'),
        ];
    }
}
