<?php

namespace App\Console\Commands;

use App\Models\Claim;
use App\Services\ClaimTelegramNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendClaimTelegramNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'claim:telegram-notify 
                            {claim_id : ID Klaim} 
                            {event : Nama Event Notifikasi (new_claim, asm_approved, rgm_approved, jejen_approved, admin_approved, rejected, disbursed)}
                            {--role= : Role penolak (opsional untuk event rejected)}
                            {--reason= : Alasan penolakan (opsional untuk event rejected)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim notifikasi Telegram klaim di background secara non-blocking';

    /**
     * Execute the console command.
     */
    public function handle(ClaimTelegramNotificationService $service): int
    {
        $claimId = (int) $this->argument('claim_id');
        $event = (string) $this->argument('event');

        $claim = Claim::with(['user', 'employee', 'effective_employee.supervisor'])->find($claimId);
        if (!$claim) {
            Log::warning("SendClaimTelegramNotification: Klaim #{$claimId} tidak ditemukan. Pengiriman dibatalkan.");
            return Command::FAILURE;
        }

        try {
            switch ($event) {
                case 'new_claim':
                case 'DIAJUKAN':
                    $service->notifyNewClaim($claim);
                    break;

                case 'asm_approved':
                case 'ACC_ASM':
                    $service->notifyAsmApproved($claim);
                    break;

                case 'rgm_approved':
                case 'ACC_RGM':
                    $service->notifyRgmApproved($claim);
                    break;

                case 'jejen_approved':
                case 'ACC_PAK_JEJEN':
                    $service->notifyJejenApproved($claim);
                    break;

                case 'admin_approved':
                case 'DISETUJUI':
                    $service->notifyAdminApproved($claim);
                    break;

                case 'rejected':
                case 'DITOLAK':
                case 'DITOLAK_FINANCE':
                    $role = (string) ($this->option('role') ?: 'Atasan / Verifikator');
                    $reason = $this->option('reason') ?: $claim->rejection_reason;
                    $service->notifyClaimRejected($claim, $role, $reason);
                    break;

                case 'disbursed':
                case 'Sudah Dicairkan':
                    $service->notifyClaimDisbursed($claim);
                    break;

                default:
                    Log::warning("SendClaimTelegramNotification: Event '{$event}' tidak dikenali.");
                    return Command::FAILURE;
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            Log::error("SendClaimTelegramNotification error pada klaim #{$claimId} ({$event}): " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
