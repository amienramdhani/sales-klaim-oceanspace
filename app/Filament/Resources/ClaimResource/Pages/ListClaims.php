<?php

namespace App\Filament\Resources\ClaimResource\Pages;

use App\Filament\Resources\ClaimResource;
use App\Models\Claim;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListClaims extends ListRecords
{
    protected static string $resource = ClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Tambah Transaksi Klaim')
                ->visible(fn () => !auth()->user()?->isFinance()),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();

        return [
            'all' => Tab::make('Semua Pengajuan')
                ->icon('heroicon-o-queue-list')
                ->badge(fn () => Claim::query()->visibleToUser($user)->count()),

            'waiting_transfer' => Tab::make('Menunggu Transfer')
                ->icon('heroicon-o-clock')
                ->badge(fn () => Claim::query()->visibleToUser($user)
                    ->where('disbursement_status', 'Belum Dicairkan')
                    ->where('approval_status', '!=', 'DITOLAK')
                    ->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('disbursement_status', 'Belum Dicairkan')
                    ->where('approval_status', '!=', 'DITOLAK')),

            'disbursed' => Tab::make('Sudah Dicairkan')
                ->icon('heroicon-o-check-circle')
                ->badge(fn () => Claim::query()->visibleToUser($user)
                    ->where('disbursement_status', 'Sudah Dicairkan')
                    ->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('disbursement_status', 'Sudah Dicairkan')),

            'rejected' => Tab::make('Ditolak')
                ->icon('heroicon-o-x-circle')
                ->badge(fn () => Claim::query()->visibleToUser($user)
                    ->where('approval_status', 'DITOLAK')
                    ->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->where('approval_status', 'DITOLAK')),
        ];
    }
}
