<?php

namespace App\Filament\Widgets;

use App\Models\Claim;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClaimStatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();

        // Ambil filter dari Dashboard Page
        $filters = $this->filters;
        $selectedMonth = $filters['month'] ?? (int)now()->month;
        $selectedYear = (int)($filters['year'] ?? now()->year);
        $isAllMonth = ($selectedMonth === 'all');
        $m = $isAllMonth ? (int)now()->month : (int)$selectedMonth;
        $y = $selectedYear;

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $periodLabel = $isAllMonth ? "Tahun {$y}" : (($monthNames[(int)$selectedMonth] ?? "Bulan {$selectedMonth}") . " {$y}");

        // Query klaim yang sesuai filter bulan & tahun
        $query = Claim::query()->visibleToUser($user);
        if ($isAllMonth) {
            $query->where(function ($q) use ($y) {
                $q->where(function ($sub) use ($y) {
                    $sub->whereNotNull('claim_date')->whereYear('claim_date', $y);
                })->orWhere(function ($sub) use ($y) {
                    $sub->whereNull('claim_date')->whereYear('created_at', $y);
                });
            });
        } else {
            $query->where(function ($q) use ($m, $y) {
                $q->where(function ($sub) use ($m, $y) {
                    $sub->whereNotNull('claim_date')
                        ->whereMonth('claim_date', $m)
                        ->whereYear('claim_date', $y);
                })->orWhere(function ($sub) use ($m, $y) {
                    $sub->whereNull('claim_date')
                        ->whereMonth('created_at', $m)
                        ->whereYear('created_at', $y);
                });
            });
        }
        $allClaims = $query->get();

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

        // 1. Tampilan Khusus Executive Approver (Pak Jejen, Bu Nurbaiti, Pak Yoga, Bu Thia)
        if ($user && $user->isExecutiveApprover()) {
            $pendingQuery = match (true) {
                $user->isYoga() => Claim::pendingYogaApproval(),
                $user->isNurbaiti() => Claim::pendingNurbaitiApproval(),
                $user->isThia() => Claim::pendingThiaApproval(),
                default => Claim::pendingJejenApproval($user),
            };

            $approvedQuery = match (true) {
                $user->isYoga() => Claim::where('approved_by_yoga_id', $user->id),
                $user->isNurbaiti() => Claim::where('approved_by_nurbaiti_id', $user->id),
                $user->isThia() => Claim::where('approved_by_thia_id', $user->id),
                default => Claim::where('approved_by_jejen_id', $user->id),
            };

            // Filter approved query berdasarkan periode terpilih
            if ($isAllMonth) {
                $approvedQuery->where(function ($q) use ($y) {
                    $q->where(function ($sub) use ($y) {
                        $sub->whereNotNull('claim_date')->whereYear('claim_date', $y);
                    })->orWhere(function ($sub) use ($y) {
                        $sub->whereNull('claim_date')->whereYear('created_at', $y);
                    });
                });
            } else {
                $approvedQuery->where(function ($q) use ($m, $y) {
                    $q->where(function ($sub) use ($m, $y) {
                        $sub->whereNotNull('claim_date')
                            ->whereMonth('claim_date', $m)
                            ->whereYear('claim_date', $y);
                    })->orWhere(function ($sub) use ($m, $y) {
                        $sub->whereNull('claim_date')
                            ->whereMonth('created_at', $m)
                            ->whereYear('created_at', $y);
                    });
                });
            }

            $pendingCount = $pendingQuery->count();
            $pendingAmount = (float)$pendingQuery->sum('amount');

            $approvedCount = $approvedQuery->count();
            $approvedAmount = (float)$approvedQuery->sum('amount');

            $disbursedClaims = (clone $approvedQuery)->where('disbursement_status', 'Sudah Dicairkan');
            $disbursedCount = $disbursedClaims->count();
            $disbursedAmount = (float)$disbursedClaims->sum('amount');

            return [
                Stat::make('Menunggu Persetujuan', $pendingCount . ' Pengajuan')
                    ->description('Total Antrean: Rp ' . number_format($pendingAmount, 0, ',', '.'))
                    ->descriptionIcon('heroicon-m-clock')
                    ->color($pendingCount > 0 ? 'warning' : 'success'),

                Stat::make('Telah Disetujui (' . $periodLabel . ')', $approvedCount . ' Pengajuan')
                    ->description('Total: Rp ' . number_format($approvedAmount, 0, ',', '.'))
                    ->descriptionIcon('heroicon-m-check-badge')
                    ->color('primary'),

                Stat::make('Telah Dicairkan (' . $periodLabel . ')', $disbursedCount . ' Pengajuan')
                    ->description('Total: Rp ' . number_format($disbursedAmount, 0, ',', '.'))
                    ->descriptionIcon('heroicon-m-banknotes')
                    ->color('success'),
            ];
        }

        // 2. Tampilan Khusus Sales
        if ($user && $user->isSales()) {
            $emp = $user->effective_employee;

            $perdinBudget = (float)($emp?->perdin_budget ?? 0);
            $usedPerdin = (float)($emp ? $emp->getUsedPerdinForPeriod($m, $y) : 0);
            $sisaPerdin = $perdinBudget - $usedPerdin;

            $serviceBudget = (float)($emp?->service_motor_budget ?? 0);
            $usedService = (float)($emp ? $emp->getUsedTransportForPeriod($m, $y) : 0);
            $sisaService = $serviceBudget - $usedService;

            $totalBudget = $perdinBudget + $serviceBudget;
            $totalUsed = $usedPerdin + $usedService;
            $totalSisa = $totalBudget - $totalUsed;

            return [
                Stat::make('Sisa Budget (' . $periodLabel . ')', ($totalSisa < 0 ? '-Rp ' : 'Rp ') . number_format(abs($totalSisa), 0, ',', '.'))
                    ->description('Plafon: Rp ' . number_format($totalBudget, 0, ',', '.') . ' | Terpakai: Rp ' . number_format($totalUsed, 0, ',', '.'))
                    ->descriptionIcon('heroicon-m-banknotes')
                    ->color($totalSisa > 0 ? 'success' : ($totalBudget > 0 ? 'danger' : 'gray')),

                Stat::make('Sisa Budget Perdin (' . $periodLabel . ')', ($sisaPerdin < 0 ? '-Rp ' : 'Rp ') . number_format(abs($sisaPerdin), 0, ',', '.'))
                    ->description('Plafon: Rp ' . number_format($perdinBudget, 0, ',', '.') . ' | Terpakai: Rp ' . number_format($usedPerdin, 0, ',', '.'))
                    ->descriptionIcon('heroicon-m-briefcase')
                    ->color($sisaPerdin > 0 ? 'success' : ($perdinBudget > 0 ? 'danger' : 'gray')),

                Stat::make('Sisa Service Motor (' . $periodLabel . ')', ($sisaService < 0 ? '-Rp ' : 'Rp ') . number_format(abs($sisaService), 0, ',', '.'))
                    ->description('Plafon: Rp ' . number_format($serviceBudget, 0, ',', '.') . ' | Terpakai: Rp ' . number_format($usedService, 0, ',', '.'))
                    ->descriptionIcon('heroicon-m-wrench-screwdriver')
                    ->color($sisaService > 0 ? 'success' : ($serviceBudget > 0 ? 'danger' : 'gray')),

                Stat::make('Total Klaim Anda (' . $periodLabel . ')', 'Rp ' . number_format($totalBiaya, 0, ',', '.'))
                    ->description("{$totalClaimsCount} total transaksi diajukan")
                    ->descriptionIcon('heroicon-m-clipboard-document-list')
                    ->color('primary'),

                Stat::make('Dana Belum Dicairkan (' . $periodLabel . ')', 'Rp ' . number_format($belumCairTotal, 0, ',', '.'))
                    ->description("{$belumCairCount} pengajuan menunggu pencairan")
                    ->descriptionIcon('heroicon-m-clock')
                    ->color('danger'),

                Stat::make('Dana Sudah Dicairkan (' . $periodLabel . ')', 'Rp ' . number_format($sudahCairTotal, 0, ',', '.'))
                    ->description("{$sudahCairCount} pengajuan telah dicairkan")
                    ->descriptionIcon('heroicon-m-check-badge')
                    ->color('success'),
            ];
        }

        // 3. Tampilan Khusus ASM & RGM
        if ($user && ($user->isAsm() || $user->isRgm())) {
            $emp = $user->effective_employee;

            $bbmBudget = (float)($emp?->bbm_budget ?? 0);
            $usedBbm = (float)($emp ? $emp->getUsedBbmForPeriod($m, $y) : 0);
            $sisaBbm = $bbmBudget - $usedBbm;

            $entBudget = (float)($emp?->entertain_budget ?? 0);
            $usedEnt = (float)($emp ? $emp->getUsedEntertainForPeriod($m, $y, true) : 0);
            $sisaEnt = $entBudget - $usedEnt;

            $perdinBudget = (float)($emp?->perdin_budget ?? 0);
            $usedPerdin = (float)($emp ? $emp->getUsedPerdinForPeriod($m, $y) : 0);
            $sisaPerdin = $perdinBudget - $usedPerdin;

            $totalBudget = $bbmBudget + $entBudget + $perdinBudget;
            $totalUsed = $usedBbm + $usedEnt + $usedPerdin;
            $totalSisa = $totalBudget - $totalUsed;

            return [
                Stat::make('Sisa Budget (' . $periodLabel . ')', ($totalSisa < 0 ? '-Rp ' : 'Rp ') . number_format(abs($totalSisa), 0, ',', '.'))
                    ->description('Plafon: Rp ' . number_format($totalBudget, 0, ',', '.') . ' | Terpakai: Rp ' . number_format($totalUsed, 0, ',', '.'))
                    ->descriptionIcon('heroicon-m-banknotes')
                    ->color($totalSisa > 0 ? 'success' : ($totalBudget > 0 ? 'danger' : 'gray')),

                Stat::make('Sisa Budget BBM (' . $periodLabel . ')', ($sisaBbm < 0 ? '-Rp ' : 'Rp ') . number_format(abs($sisaBbm), 0, ',', '.'))
                    ->description('Plafon: Rp ' . number_format($bbmBudget, 0, ',', '.') . ' | Terpakai: Rp ' . number_format($usedBbm, 0, ',', '.'))
                    ->descriptionIcon('heroicon-m-truck')
                    ->color($sisaBbm > 0 ? 'success' : ($bbmBudget > 0 ? 'danger' : 'gray')),

                Stat::make('Sisa Budget Entertain (' . $periodLabel . ')', ($sisaEnt < 0 ? '-Rp ' : 'Rp ') . number_format(abs($sisaEnt), 0, ',', '.'))
                    ->description('Plafon: Rp ' . number_format($entBudget, 0, ',', '.') . ' | Terpakai: Rp ' . number_format($usedEnt, 0, ',', '.'))
                    ->descriptionIcon('heroicon-m-sparkles')
                    ->color($sisaEnt > 0 ? 'success' : ($entBudget > 0 ? 'danger' : 'gray')),

                Stat::make('Sisa Budget Perdin (' . $periodLabel . ')', ($sisaPerdin < 0 ? '-Rp ' : 'Rp ') . number_format(abs($sisaPerdin), 0, ',', '.'))
                    ->description('Plafon: Rp ' . number_format($perdinBudget, 0, ',', '.') . ' | Terpakai: Rp ' . number_format($usedPerdin, 0, ',', '.'))
                    ->descriptionIcon('heroicon-m-briefcase')
                    ->color($sisaPerdin > 0 ? 'success' : ($perdinBudget > 0 ? 'danger' : 'gray')),

                Stat::make('Dana Belum Dicairkan (' . $periodLabel . ')', 'Rp ' . number_format($belumCairTotal, 0, ',', '.'))
                    ->description("{$belumCairCount} pengajuan menunggu pencairan")
                    ->descriptionIcon('heroicon-m-clock')
                    ->color('danger'),

                Stat::make('Dana Sudah Dicairkan (' . $periodLabel . ')', 'Rp ' . number_format($sudahCairTotal, 0, ',', '.'))
                    ->description("{$sudahCairCount} pengajuan telah dicairkan")
                    ->descriptionIcon('heroicon-m-check-badge')
                    ->color('success'),
            ];
        }

        // 4. Tampilan Umum (Superadmin, Admin, Finance, Kasir)
        return [
            Stat::make('Total Biaya Klaim (' . $periodLabel . ')', 'Rp ' . number_format($totalBiaya, 0, ',', '.'))
                ->description("{$totalClaimsCount} total transaksi klaim operasional")
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),

            Stat::make('Nota Balik BBM (' . $periodLabel . ')', 'Rp ' . number_format($bbmTotal, 0, ',', '.'))
                ->description("{$bbmCount} item pengisian bahan bakar")
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make('Klaim Entertain (' . $periodLabel . ')', 'Rp ' . number_format($entertainTotal, 0, ',', '.'))
                ->description("{$entertainCount} item pengeluaran entertain")
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('warning'),

            Stat::make('Klaim Perjalanan Dinas (' . $periodLabel . ')', 'Rp ' . number_format($perdinTotal, 0, ',', '.'))
                ->description("{$perdinCount} pengajuan perjalanan dinas")
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary'),

            Stat::make('Dana Belum Dicairkan (' . $periodLabel . ')', 'Rp ' . number_format($belumCairTotal, 0, ',', '.'))
                ->description("{$belumCairCount} pengajuan menunggu pencairan")
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger'),

            Stat::make('Dana Sudah Dicairkan (' . $periodLabel . ')', 'Rp ' . number_format($sudahCairTotal, 0, ',', '.'))
                ->description("{$sudahCairCount} pengajuan telah dicairkan")
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}
