<?php

namespace App\Filament\Widgets;

use App\Models\Claim;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClaimStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();
        $allClaims = Claim::query()->visibleToUser($user)->get();

        $totalClaimsCount = $allClaims->count();
        $totalBiaya = (float)$allClaims->sum('amount');

        $entertainTotal = 0;
        $entertainCount = 0;
        $bbmTotal = 0;
        $bbmCount = 0;
        $perdinTotal = 0;
        $perdinCount = 0;
        $transportTotal = 0;
        $transportCount = 0;

        foreach ($allClaims as $claim) {
            if ($claim->is_perdin) {
                $perdinTotal += (float)$claim->amount;
                $perdinCount++;
                continue;
            }

            foreach ($claim->getLineItems() as $item) {
                $itemType = is_array($item['claim_type'] ?? null) ? implode(', ', $item['claim_type']) : ($item['claim_type'] ?? '');
                $amount = (float)($item['amount'] ?? 0);
                $upperType = strtoupper($itemType);

                if (str_contains($upperType, 'ENTERTAIN')) {
                    $entertainTotal += $amount;
                    $entertainCount++;
                } elseif (str_contains($upperType, 'BBM')) {
                    $bbmTotal += $amount;
                    $bbmCount++;
                } elseif (str_contains($upperType, 'PERDIN') || str_contains($upperType, 'PERJALANAN')) {
                    $perdinTotal += $amount;
                    $perdinCount++;
                } else {
                    $transportTotal += $amount;
                    $transportCount++;
                }
            }
        }

        $belumCairClaims = $allClaims->where('disbursement_status', 'Belum Dicairkan');
        $belumCairTotal = (float)$belumCairClaims->sum('amount');
        $belumCairCount = $belumCairClaims->count();

        $sudahCairClaims = $allClaims->where('disbursement_status', 'Sudah Dicairkan');
        $sudahCairTotal = (float)$sudahCairClaims->sum('amount');
        $sudahCairCount = $sudahCairClaims->count();

        return [
            Stat::make('Total Biaya Klaim', 'Rp ' . number_format($totalBiaya, 0, ',', '.'))
                ->description("{$totalClaimsCount} total transaksi klaim operasional")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Klaim Entertain', 'Rp ' . number_format($entertainTotal, 0, ',', '.'))
                ->description("{$entertainCount} item pengeluaran entertain")
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('warning'),

            Stat::make('Klaim BBM', 'Rp ' . number_format($bbmTotal, 0, ',', '.'))
                ->description("{$bbmCount} item pengisian bahan bakar")
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make('Klaim Perjalanan Dinas', 'Rp ' . number_format($perdinTotal, 0, ',', '.'))
                ->description("{$perdinCount} pengajuan perjalanan dinas")
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary'),

            Stat::make('Dana Belum Dicairkan', 'Rp ' . number_format($belumCairTotal, 0, ',', '.'))
                ->description("{$belumCairCount} pengajuan menunggu pencairan")
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger'),

            Stat::make('Dana Sudah Dicairkan', 'Rp ' . number_format($sudahCairTotal, 0, ',', '.'))
                ->description("{$sudahCairCount} pengajuan telah dicairkan")
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}
