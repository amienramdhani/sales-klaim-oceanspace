<?php

namespace App\Filament\Resources\PerdinClaimResource\Pages;

use App\Filament\Resources\PerdinClaimResource;
use App\Models\Claim;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPerdinClaims extends ListRecords
{
    protected static string $resource = PerdinClaimResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Tambah Pengajuan Perdin'),
        ];
    }

    public function getTabs(): array
    {
        $user = auth()->user();
        $empId = $user?->getEffectiveEmployeeId();

        if ($user && !$user->isSuperAdmin() && !$user->isAdmin() && !$user->isFinance()) {
            $tabs = [
                'my_claims' => Tab::make('Pengajuan Saya')
                    ->icon('heroicon-o-user')
                    ->badge(fn () => Claim::where('claim_category', 'perdin')->where('employee_id', $empId)->count())
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('employee_id', $empId ?: -1)),
            ];

            if ($user->isAsm()) {
                $tabs['need_my_approval'] = Tab::make('Menunggu Approval ASM')
                    ->icon('heroicon-o-check-badge')
                    ->badge(fn () => Claim::where('claim_category', 'perdin')->where('approval_status', 'DIAJUKAN')->count())
                    ->badgeColor('warning')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('approval_status', 'DIAJUKAN'));
            } elseif ($user->isRgm()) {
                $tabs['need_my_approval'] = Tab::make('Menunggu Approval RGM')
                    ->icon('heroicon-o-check-badge')
                    ->badge(fn () => Claim::where('claim_category', 'perdin')->whereIn('approval_status', ['ACC_ASM', 'DIAJUKAN'])->count())
                    ->badgeColor('warning')
                    ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('approval_status', ['ACC_ASM', 'DIAJUKAN']));
            } elseif ($user->isJejen()) {
                $tabs['need_my_approval'] = Tab::make('Menunggu Approval Pak Jejen')
                    ->icon('heroicon-o-star')
                    ->badge(fn () => Claim::where('claim_category', 'perdin')->where('approval_status', 'ACC_RGM')->count())
                    ->badgeColor('success')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('approval_status', 'ACC_RGM'));
            }

            return $tabs;
        }

        // Admin, SuperAdmin, Finance Tabs
        return [
            'all' => Tab::make('Semua Perdin')
                ->icon('heroicon-o-queue-list')
                ->badge(fn () => Claim::where('claim_category', 'perdin')->count()),

            'waiting_approval' => Tab::make('Menunggu Approval')
                ->icon('heroicon-o-clock')
                ->badge(fn () => Claim::where('claim_category', 'perdin')->whereIn('approval_status', ['DIAJUKAN', 'ACC_ASM', 'ACC_RGM'])->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('approval_status', ['DIAJUKAN', 'ACC_ASM', 'ACC_RGM'])),

            'waiting_gform' => Tab::make('Perlu G-Form Admin')
                ->icon('heroicon-o-arrow-up-tray')
                ->badge(fn () => Claim::where('claim_category', 'perdin')->where('admin_gform_submitted', false)->whereIn('approval_status', ['ACC_RGM', 'ACC_PAK_JEJEN', 'DISETUJUI'])->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('admin_gform_submitted', false)->whereIn('approval_status', ['ACC_RGM', 'ACC_PAK_JEJEN', 'DISETUJUI'])),

            'waiting_finance' => Tab::make('Menunggu Pencairan Finance')
                ->icon('heroicon-o-banknotes')
                ->badge(fn () => Claim::where('claim_category', 'perdin')->where('admin_gform_submitted', true)->where('disbursement_status', 'Belum Dicairkan')->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('admin_gform_submitted', true)->where('disbursement_status', 'Belum Dicairkan')),

            'nota_balik' => Tab::make('Nota Balik & Sisa Dana')
                ->icon('heroicon-o-receipt-percent')
                ->badge(fn () => Claim::where('claim_category', 'perdin')->where('disbursement_status', 'Sudah Dicairkan')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('disbursement_status', 'Sudah Dicairkan')),
        ];
    }
}
