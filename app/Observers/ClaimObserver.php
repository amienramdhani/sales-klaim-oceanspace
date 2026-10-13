<?php

namespace App\Observers;

use App\Models\Claim;
use App\Services\ClaimTelegramNotificationService;
use Illuminate\Support\Facades\Log;

class ClaimObserver
{
    /**
     * Handle the Claim "created" event.
     */
    public function created(Claim $claim): void
    {
        try {
            // Ketika klaim baru dibuat, kirim notifikasi sesuai status awal
            if ($claim->approval_status === 'ACC_ASM') {
                app(ClaimTelegramNotificationService::class)->dispatchAsync($claim->id, 'asm_approved');
            } elseif ($claim->approval_status === 'ACC_RGM') {
                app(ClaimTelegramNotificationService::class)->dispatchAsync($claim->id, 'rgm_approved');
            } elseif ($claim->approval_status === 'DIAJUKAN') {
                app(ClaimTelegramNotificationService::class)->dispatchAsync($claim->id, 'new_claim');
            }
        } catch (\Throwable $e) {
            Log::error("ClaimObserver created error pada klaim #{$claim->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the Claim "updated" event.
     */
    public function updated(Claim $claim): void
    {
        try {
            $notificationService = app(ClaimTelegramNotificationService::class);

            // 1. Deteksi Perubahan Status Persetujuan (approval_status)
            if ($claim->wasChanged('approval_status')) {
                $newStatus = $claim->approval_status;

                switch ($newStatus) {
                    case 'DIAJUKAN':
                        // Kasus: Pemohon mengajukan ulang setelah revisi / perbaikan
                        $notificationService->dispatchAsync($claim->id, 'new_claim');
                        break;

                    case 'ACC_ASM':
                        // Disetujui ASM -> Notif ke RGM
                        $notificationService->dispatchAsync($claim->id, 'asm_approved');
                        break;

                    case 'ACC_RGM':
                        // Disetujui RGM -> Notif ke Admin
                        $notificationService->dispatchAsync($claim->id, 'rgm_approved');
                        break;

                    case 'ACC_ADMIN':
                        // Diverifikasi Admin -> Notif ke Head of Sales (Pak Jejen)
                        $notificationService->dispatchAsync($claim->id, 'admin_approved');
                        break;

                    case 'WAITING_YOGA':
                        // Disetujui Head of Sales (Pak Jejen) untuk PT Media Selular Indonesia -> Notif ke Manager Finance (Pak Yoga P. Hadi)
                        $notificationService->dispatchAsync($claim->id, 'waiting_yoga');
                        break;

                    case 'WAITING_THIA':
                        // Disetujui Head of Sales (Pak Jejen) untuk CV Top Selular -> Notif ke Manajer Keuangan (Thia Aisyah)
                        $notificationService->dispatchAsync($claim->id, 'waiting_thia');
                        break;

                    case 'WAITING_NURBAITI':
                        // Disetujui Manager Finance (Pak Yoga P. Hadi) -> Notif ke Finance Coordinator (Bu Nurbaeti Riaseha)
                        $notificationService->dispatchAsync($claim->id, 'waiting_nurbaiti');
                        break;

                    case 'DISETUJUI':
                        // Disetujui penuh (Nurbaeti / Thia) -> Notif ke Finance sesuai Badan Usaha untuk antrean pencairan
                        $notificationService->dispatchAsync($claim->id, 'approved_for_disbursement');
                        break;

                    case 'DITOLAK_FINANCE':
                        // Ditolak oleh Finance -> Notif ke Pemohon & Admin
                        $notificationService->dispatchAsync($claim->id, 'rejected', [
                            'role' => 'Finance',
                            'reason' => $claim->rejection_reason,
                        ]);
                        break;

                    case 'DITOLAK':
                        // Ditolak oleh Atasan / Admin -> Notif ke Pemohon
                        $role = 'Atasan / Verifikator';
                        if (auth()->check()) {
                            $u = auth()->user();
                            if ($u->isJejen()) $role = 'Head of Sales (Pak Jejen)';
                            elseif ($u->isYoga()) $role = 'Manager Finance (Pak Yoga P. Hadi)';
                            elseif ($u->isNurbaiti()) $role = 'Finance Coordinator (Bu Nurbaeti Riaseha)';
                            elseif ($u->isThia()) $role = 'Manajer Keuangan (Thia Aisyah)';
                            elseif ($u->isRgm()) $role = 'RGM';
                            elseif ($u->isAsm()) $role = 'ASM';
                            elseif ($u->isAdmin()) $role = 'Admin';
                        }
                        $notificationService->dispatchAsync($claim->id, 'rejected', [
                            'role' => $role,
                            'reason' => $claim->rejection_reason,
                        ]);
                        break;
                }
            }

            // 2. Deteksi Perubahan Status Pencairan (disbursement_status)
            if ($claim->wasChanged('disbursement_status') && $claim->disbursement_status === 'Sudah Dicairkan') {
                $notificationService->dispatchAsync($claim->id, 'disbursed');
            }
        } catch (\Throwable $e) {
            Log::error("ClaimObserver updated error pada klaim #{$claim->id}: " . $e->getMessage());
        }
    }
}
