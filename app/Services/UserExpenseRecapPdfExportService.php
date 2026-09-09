<?php

namespace App\Services;

use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;

class UserExpenseRecapPdfExportService
{
    /**
     * Export all employees budget & expense recap to PDF (Landscape)
     */
    public function exportAllUsersRecapPdf(iterable $employees, ?int $month = null, ?int $year = null): Response
    {
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $periodLabel = ($month && isset($monthNames[$month]) ? $monthNames[$month] : 'Semua Bulan') . ($year ? " {$year}" : '');

        $rows = [];
        $summary = [
            'total_bbm_budget' => 0,
            'total_bbm_used' => 0,
            'total_bbm_remaining' => 0,

            'total_ent_budget' => 0,
            'total_ent_used' => 0,
            'total_ent_remaining' => 0,

            'total_perdin_budget' => 0,
            'total_perdin_used' => 0,
            'total_perdin_remaining' => 0,

            'total_trans_budget' => 0,
            'total_trans_used' => 0,
            'total_trans_remaining' => 0,

            'total_budget' => 0,
            'total_used' => 0,
            'total_remaining' => 0,
        ];

        foreach ($employees as $emp) {
            $bbmBudget = (float)$emp->bbm_budget;
            $bbmUsed = (float)$emp->getUsedBbmForPeriod($month, $year);
            $bbmRemaining = $bbmBudget - $bbmUsed;

            $entBudget = (float)$emp->entertain_budget;
            $entUsed = (float)$emp->getUsedEntertainForPeriod($month, $year, true);
            $entRemaining = $entBudget - $entUsed;

            $perdinBudget = (float)$emp->perdin_budget;
            $perdinUsed = (float)$emp->getUsedPerdinForPeriod($month, $year);
            $perdinRemaining = $perdinBudget - $perdinUsed;

            $transBudget = (float)$emp->service_motor_budget;
            $transUsed = (float)$emp->getUsedTransportForPeriod($month, $year);
            $transRemaining = $transBudget - $transUsed;

            $totalBudget = (float)$emp->total_budget;
            $totalUsed = (float)$emp->getTotalExpenseUsedForPeriod($month, $year);
            $totalRemaining = $totalBudget - $totalUsed;

            $isOver = ($bbmBudget > 0 && $bbmUsed > $bbmBudget) ||
                      ($entBudget > 0 && $entUsed > $entBudget) ||
                      ($perdinBudget > 0 && $perdinUsed > $perdinBudget) ||
                      ($transBudget > 0 && $transUsed > $transBudget) ||
                      ($totalBudget > 0 && $totalUsed > $totalBudget);

            $rows[] = [
                'name' => $emp->name,
                'position' => $emp->position_name ?? ($emp->role?->name ?? 'Sales'),
                'bbm_budget' => $bbmBudget,
                'bbm_used' => $bbmUsed,
                'bbm_remaining' => $bbmRemaining,
                'ent_budget' => $entBudget,
                'ent_used' => $entUsed,
                'ent_remaining' => $entRemaining,
                'perdin_budget' => $perdinBudget,
                'perdin_used' => $perdinUsed,
                'perdin_remaining' => $perdinRemaining,
                'trans_budget' => $transBudget,
                'trans_used' => $transUsed,
                'trans_remaining' => $transRemaining,
                'total_budget' => $totalBudget,
                'total_used' => $totalUsed,
                'total_remaining' => $totalRemaining,
                'is_over' => $isOver,
            ];

            $summary['total_bbm_budget'] += $bbmBudget;
            $summary['total_bbm_used'] += $bbmUsed;
            $summary['total_bbm_remaining'] += $bbmRemaining;

            $summary['total_ent_budget'] += $entBudget;
            $summary['total_ent_used'] += $entUsed;
            $summary['total_ent_remaining'] += $entRemaining;

            $summary['total_perdin_budget'] += $perdinBudget;
            $summary['total_perdin_used'] += $perdinUsed;
            $summary['total_perdin_remaining'] += $perdinRemaining;

            $summary['total_trans_budget'] += $transBudget;
            $summary['total_trans_used'] += $transUsed;
            $summary['total_trans_remaining'] += $transRemaining;

            $summary['total_budget'] += $totalBudget;
            $summary['total_used'] += $totalUsed;
            $summary['total_remaining'] += $totalRemaining;
        }

        $pdf = Pdf::loadView('exports.user-budget-recap-pdf', [
            'employees' => $rows,
            'summary' => $summary,
            'periodLabel' => $periodLabel,
        ])->setPaper('a4', 'landscape');

        $filename = "Rekap_Budget_Sisa_Saldo_Karyawan_" . date('Ymd_His') . ".pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Export single employee budget & claim history to PDF (Portrait)
     */
    public function exportSingleUserRecapPdf(Employee $employee, ?int $month = null, ?int $year = null): Response
    {
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $periodLabel = ($month && isset($monthNames[$month]) ? $monthNames[$month] : 'Semua Bulan') . ($year ? " {$year}" : '');

        $bbmBudget = (float)$employee->bbm_budget;
        $bbmUsed = (float)$employee->getUsedBbmForPeriod($month, $year);

        $entBudget = (float)$employee->entertain_budget;
        $entUsed = (float)$employee->getUsedEntertainForPeriod($month, $year, true);

        $perdinBudget = (float)$employee->perdin_budget;
        $perdinUsed = (float)$employee->getUsedPerdinForPeriod($month, $year);

        $transBudget = (float)$employee->service_motor_budget;
        $transUsed = (float)$employee->getUsedTransportForPeriod($month, $year);

        $totalUsed = (float)$employee->getTotalExpenseUsedForPeriod($month, $year);

        $categoryBreakdown = [
            [
                'name' => 'BBM (Bahan Bakar Minyak)',
                'budget' => $bbmBudget,
                'used' => $bbmUsed,
                'remaining' => $bbmBudget - $bbmUsed,
            ],
            [
                'name' => 'Entertain (Makan & Relasi)',
                'budget' => $entBudget,
                'used' => $entUsed,
                'remaining' => $entBudget - $entUsed,
            ],
            [
                'name' => 'Perjalanan Dinas (Perdin)',
                'budget' => $perdinBudget,
                'used' => $perdinUsed,
                'remaining' => $perdinBudget - $perdinUsed,
            ],
            [
                'name' => 'Service Motor / Transport',
                'budget' => $transBudget,
                'used' => $transUsed,
                'remaining' => $transBudget - $transUsed,
            ],
        ];

        $claimsQuery = $employee->claims();
        if ($month) $claimsQuery->whereMonth('claim_date', $month);
        if ($year) $claimsQuery->whereYear('claim_date', $year);
        $claims = $claimsQuery->orderBy('claim_date', 'desc')->get();

        $pdf = Pdf::loadView('exports.single-user-budget-recap-pdf', [
            'employee' => $employee,
            'categoryBreakdown' => $categoryBreakdown,
            'totalUsed' => $totalUsed,
            'claims' => $claims,
            'periodLabel' => $periodLabel,
        ])->setPaper('a4', 'portrait');

        $cleanName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $employee->name);
        $filename = "Rekap_Budget_{$cleanName}_" . date('Ymd_His') . ".pdf";

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
