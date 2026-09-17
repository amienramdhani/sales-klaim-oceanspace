<?php

namespace App\Services;

use App\Models\Claim;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ClaimTelegramNotificationService
{
    protected TelegramService $telegram;

    public function __construct(TelegramService $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Dispatch pengiriman notifikasi Telegram di background secara non-blocking
     * Mengembalikan response langsung ke Filament dalam < 50ms sehingga halaman web tidak loading lama.
     */
    public function dispatchAsync(int $claimId, string $event, array $options = []): void
    {
        dispatch(function () use ($claimId, $event, $options) {
            $claim = Claim::with(['user', 'employee', 'effective_employee.supervisor'])->find($claimId);
            if (!$claim) {
                return;
            }

            try {
                switch ($event) {
                    case 'new_claim':
                    case 'DIAJUKAN':
                        $this->notifyNewClaim($claim);
                        break;

                    case 'asm_approved':
                    case 'ACC_ASM':
                        $this->notifyAsmApproved($claim);
                        break;

                    case 'rgm_approved':
                    case 'ACC_RGM':
                        $this->notifyRgmApproved($claim);
                        break;

                    case 'jejen_approved':
                    case 'ACC_PAK_JEJEN':
                        $this->notifyJejenApproved($claim);
                        break;

                    case 'admin_approved':
                    case 'DISETUJUI':
                        $this->notifyAdminApproved($claim);
                        break;

                    case 'rejected':
                    case 'DITOLAK':
                    case 'DITOLAK_FINANCE':
                        $role = $options['role'] ?? 'Atasan / Verifikator';
                        $reason = $options['reason'] ?? $claim->rejection_reason;
                        $this->notifyClaimRejected($claim, $role, $reason);
                        break;

                    case 'disbursed':
                    case 'Sudah Dicairkan':
                        $this->notifyClaimDisbursed($claim);
                        break;
                }
            } catch (\Throwable $e) {
                Log::error("ClaimTelegramNotificationService async error pada klaim #{$claimId} ({$event}): " . $e->getMessage());
            }
        })->afterResponse();
    }

    /**
     * 1. Notifikasi Pengajuan Klaim Baru (atau Ajukan Kembali setelah revisi)
     * Mengikuti hierarki jabatan pemohon:
     * - Sales -> Kirim ke ASM
     * - ASM -> Kirim ke RGM
     * - RGM -> Kirim ke Pak Jejen
     */
    public function notifyNewClaim(Claim $claim): void
    {
        $applicantEmp = $claim->effective_employee ?? $claim->employee ?? ($claim->user?->employee_id ? Employee::find($claim->user->employee_id) : null);
        $applicantUser = $claim->user ?? ($applicantEmp ? $this->getUserForEmployee($applicantEmp) : null);

        $isApplicantRgm = $applicantEmp?->isRgm() || $applicantUser?->isRgm();
        $isApplicantAsm = !$isApplicantRgm && ($applicantEmp?->isAsm() || $applicantUser?->isAsm());

        if ($isApplicantRgm) {
            // Pemohon adalah RGM -> Kirim langsung ke Head of Sales (Pak Jejen)
            $recipients = $this->getJejenChatIds();
            $targetRole = 'Head of Sales (Pak Jejen)';
            $routeUrl = $this->getApprovalUrl('filament.admin.resources.jejen-claim-approvals.index');
        } elseif ($isApplicantAsm) {
            // Pemohon adalah ASM -> Kirim langsung ke RGM
            $recipients = $this->getRgmChatIds($claim);
            $targetRole = 'RGM (Regional General Manager)';
            $routeUrl = $this->getApprovalUrl('filament.admin.resources.rgm-claim-approvals.index');
        } else {
            // Pemohon adalah Sales / Staff -> Kirim ke ASM
            $recipients = $this->getAsmChatIds($claim);
            $targetRole = 'ASM (Area Sales Manager)';
            $routeUrl = $this->getApprovalUrl('filament.admin.resources.asm-claim-approvals.index');
        }

        if (empty($recipients)) {
            Log::info("ClaimTelegramNotificationService: Tidak ada Chat ID tujuan untuk pengajuan baru klaim #{$claim->id} ({$targetRole}).");
            return;
        }

        $html = $this->formatApprovalNotificationMessage($claim, $routeUrl);
        $buttons = $this->createButton('🔍 Buka Menu Persetujuan', $routeUrl);
        $this->telegram->sendToMultiple($recipients, $html, $buttons);
    }

    /**
     * 2. Notifikasi: ASM Telah Menyetujui -> Kirim ke RGM
     */
    public function notifyAsmApproved(Claim $claim): void
    {
        $recipients = $this->getRgmChatIds($claim);
        if (empty($recipients)) {
            Log::info("ClaimTelegramNotificationService: Tidak ada Chat ID RGM untuk klaim #{$claim->id}.");
            return;
        }

        $routeUrl = $this->getApprovalUrl('filament.admin.resources.rgm-claim-approvals.index');
        $html = $this->formatApprovalNotificationMessage($claim, $routeUrl);
        $buttons = $this->createButton('🔍 Buka Persetujuan RGM', $routeUrl);
        $this->telegram->sendToMultiple($recipients, $html, $buttons);
    }

    /**
     * 3. Notifikasi: RGM Telah Menyetujui -> Kirim ke Head of Sales (Pak Jejen)
     */
    public function notifyRgmApproved(Claim $claim): void
    {
        $recipients = $this->getJejenChatIds();
        if (empty($recipients)) {
            Log::info("ClaimTelegramNotificationService: Tidak ada Chat ID Pak Jejen untuk klaim #{$claim->id}.");
            return;
        }

        $routeUrl = $this->getApprovalUrl('filament.admin.resources.jejen-claim-approvals.index');
        $html = $this->formatApprovalNotificationMessage($claim, $routeUrl);
        $buttons = $this->createButton('🔍 Buka Persetujuan Pak Jejen', $routeUrl);
        $this->telegram->sendToMultiple($recipients, $html, $buttons);
    }

    /**
     * 4. Notifikasi: Pak Jejen Telah Menyetujui -> Kirim ke Admin
     */
    public function notifyJejenApproved(Claim $claim): void
    {
        $recipients = $this->getAdminChatIds($claim);
        if (empty($recipients)) {
            Log::info("ClaimTelegramNotificationService: Tidak ada Chat ID Admin untuk klaim #{$claim->id}.");
            return;
        }

        $routeUrl = $this->getApprovalUrl('filament.admin.resources.admin-claim-approvals.index');
        $html = $this->formatApprovalNotificationMessage($claim, $routeUrl);
        $buttons = $this->createButton('🔍 Buka Verifikasi Admin', $routeUrl);
        $this->telegram->sendToMultiple($recipients, $html, $buttons);
    }

    /**
     * 5. Notifikasi: Admin Telah Menyetujui -> Kirim ke Finance
     */
    public function notifyAdminApproved(Claim $claim): void
    {
        $recipients = $this->getFinanceChatIds();
        if (empty($recipients)) {
            Log::info("ClaimTelegramNotificationService: Tidak ada Chat ID Finance untuk klaim #{$claim->id}.");
            return;
        }

        $isBbm = $claim->claim_category === 'bbm' || str_contains(strtoupper($claim->claim_type_string ?? ''), 'BBM');
        $routeUrl = $isBbm
            ? $this->getApprovalUrl('filament.admin.resources.finance-bbm-claims.index')
            : $this->getApprovalUrl('filament.admin.resources.finance-non-bbm-claims.index');

        $html = $this->formatApprovalNotificationMessage($claim, $routeUrl);
        $buttons = $this->createButton('💸 Buka Menu Finance', $routeUrl);
        $this->telegram->sendToMultiple($recipients, $html, $buttons);
    }

    /**
     * Format pesan notifikasi persetujuan klaim sesuai format standar PT. SMI
     */
    public function formatApprovalNotificationMessage(Claim $claim, string $approvalUrl): string
    {
        $emp = $claim->effective_employee ?? $claim->employee ?? ($claim->user?->employee_id ? Employee::find($claim->user->employee_id) : null);
        $user = $claim->user ?? ($emp ? $this->getUserForEmployee($emp) : null);

        $uid = $claim->_uid ?: ('UID-' . ($claim->claim_date ? $claim->claim_date->format('Ymd-') : date('Ymd-')) . str_pad($claim->id, 4, '0', STR_PAD_LEFT));
        $email = $emp?->email ?: ($user?->email ?: '-');
        $nama = $emp?->name ?: ($user?->name ?: '-');
        $divisi = $emp?->position_name ?: ($user?->role_name ?: ($emp?->role?->name ?: '-'));

        $noRek = $emp?->bank_account_number ?: '-';
        $namaRek = $emp?->bank_account_name ?: ($emp?->name ?: '-');
        $bank = $emp?->bank_name ?: '-';
        $jumlahTransfer = $this->formatRupiah($claim->amount);
        $keperluan = $claim->purpose_string ?: ($claim->note ?: '-');
        $reffnote = $claim->reffnote ?: ($claim->branch?->reffnote ?: '-');

        return "<b>📣 NOTIFIKASI APPROVAL - PT. SATU MEDIA INDONESIA (PT. SMI)</b>\n\n"
             . "<b>UID:</b> {$uid}\n"
             . "<b>Email:</b> {$email}\n"
             . "<b>Nama:</b> {$nama}\n"
             . "<b>Divisi:</b> {$divisi}\n\n"
             . "<b>Nomor Rekening:</b> {$noRek}\n"
             . "<b>Nama Pemilik Rekening:</b> {$namaRek}\n"
             . "<b>Bank:</b> {$bank}\n"
             . "<b>Jumlah Transfer:</b> {$jumlahTransfer}\n"
             . "<b>Keperluan:</b> {$keperluan}\n"
             . "<b>Reffnote:</b> {$reffnote}\n\n\n"
             . "<b>Klik link berikut untuk Approve atau Reject:</b>\n"
             . "{$approvalUrl}";
    }

    /**
     * 6. Notifikasi: Klaim Ditolak / Memerlukan Revisi
     */
    public function notifyClaimRejected(Claim $claim, string $rejectedByRole, ?string $reason): void
    {
        // Kirim ke pemohon
        $applicantChatId = $this->getApplicantChatId($claim);
        $recipients = [];
        if (!empty($applicantChatId)) {
            $recipients[] = $applicantChatId;
        }

        // Jika ditolak oleh Finance, Admin juga mendapat notifikasi
        if (strtoupper($rejectedByRole) === 'FINANCE' || $claim->approval_status === 'DITOLAK_FINANCE') {
            $adminIds = $this->getAdminChatIds($claim);
            $recipients = array_merge($recipients, $adminIds);
        }

        if (empty($recipients)) {
            return;
        }

        $nama = $this->getApplicantName($claim);
        $kategori = $this->getCategoryLabel($claim);
        $nominal = $this->formatRupiah($claim->amount);
        $alasan = !empty($reason) ? htmlspecialchars($reason) : 'Silakan periksa lampiran atau koordinasi dengan atasan.';

        $html = "❌ <b>PEMBERITAHUAN: PENGAJUAN KLAIM DITOLAK / REVISI</b>\n"
              . "━━━━━━━━━━━━━━━━━━━━━━\n"
              . "👤 <b>Pemohon:</b> {$nama}\n"
              . "📂 <b>Kategori:</b> {$kategori}\n"
              . "💰 <b>Nominal:</b> {$nominal}\n"
              . "🛑 <b>Ditolak Oleh:</b> {$rejectedByRole}\n"
              . "📝 <b>Catatan Penolakan:</b>\n<i>\"{$alasan}\"</i>\n"
              . "━━━━━━━━━━━━━━━━━━━━━━\n"
              . "👉 <i>Silakan perbaiki data atau dokumen pengajuan Anda lalu ajukan kembali.</i>";

        $this->telegram->sendToMultiple($recipients, $html);
    }

    /**
     * 7. Notifikasi: Pencairan Dana Sukses -> Kirim ke Pemohon
     */
    public function notifyClaimDisbursed(Claim $claim): void
    {
        $applicantChatId = $this->getApplicantChatId($claim);
        if (empty($applicantChatId)) {
            return;
        }

        $nama = $this->getApplicantName($claim);
        $kategori = $this->getCategoryLabel($claim);
        $nominal = $this->formatRupiah($claim->amount);
        $tanggal = $claim->disbursed_at ? $claim->disbursed_at->format('d/m/Y H:i') : date('d/m/Y');

        $html = "🎉 <b>DANA KLAIM TELAH DICAIRKAN OLEH FINANCE</b>\n"
              . "━━━━━━━━━━━━━━━━━━━━━━\n"
              . "Halo <b>{$nama}</b>,\n"
              . "Pengajuan klaim <b>{$kategori}</b> Anda senilai <b>{$nominal}</b> telah berhasil ditransfer oleh Finance pada tanggal {$tanggal}.\n\n"
              . "💳 <i>Bukti transfer dan status pencairan dapat Anda cek di aplikasi sistem klaim.</i>\n"
              . "Terima kasih atas kerja samanya!";

        $this->telegram->sendMessage($applicantChatId, $html);
    }

    /* =========================================================================
     * RECIPIENT RESOLUTION HELPERS (DUAL ROUTING: PRIVATE CHAT + GROUP FALLBACK)
     * ========================================================================= */

    /**
     * Menemukan record User terkait dari Employee menggunakan relasi, employee_id, email, atau kemiripan nama
     */
    public function getUserForEmployee(?Employee $employee): ?User
    {
        if (!$employee) {
            return null;
        }

        if ($employee->relationLoaded('user') && $employee->user) {
            return $employee->user;
        }

        $u = User::where('employee_id', $employee->id)->first();
        if ($u) {
            return $u;
        }

        if (!empty($employee->email)) {
            $u = User::where('email', $employee->email)->first();
            if ($u) {
                return $u;
            }
        }

        $cleanName = trim(preg_replace('/\s*\(.*?\)\s*/', '', $employee->name));
        if (!empty($cleanName)) {
            $u = User::where('name', 'like', "%{$cleanName}%")
                ->orWhereRaw('? LIKE CONCAT("%", users.name, "%")', [$cleanName])
                ->first();
            if ($u) {
                return $u;
            }
        }

        return null;
    }

    /**
     * Dapatkan Chat ID untuk ASM:
     * 1. Supervisor langsung dari karyawan pemohon (jika supervisor bertindak sebagai ASM)
     * 2. ASM di region yang sama yang memiliki telegram_chat_id
     * 3. Fallback: User ASM manapun yang memiliki telegram_chat_id
     * 4. Fallback: TELEGRAM_ASM_CHAT_ID atau TELEGRAM_DEFAULT_CHAT_ID (.env)
     */
    public function getAsmChatIds(Claim $claim): array
    {
        $chatIds = [];

        // 1. Cek Atasan Langsung dari Employee
        $emp = $claim->effective_employee ?? $claim->employee ?? ($claim->user?->employee_id ? Employee::find($claim->user->employee_id) : null);
        if ($emp && $emp->supervisor) {
            $supUser = $this->getUserForEmployee($emp->supervisor);
            if ($supUser && !empty($supUser->telegram_chat_id)) {
                $chatIds[] = $supUser->telegram_chat_id;
            }
        }

        // 2. Cek User role ASM pada region terkait
        $region = $this->getClaimRegion($claim);
        $asmUsers = User::whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->get()
            ->filter(fn (User $u) => $u->isAsm() && (empty($region) || in_array($region, $u->getRegionList())));

        foreach ($asmUsers as $u) {
            $chatIds[] = $u->telegram_chat_id;
        }

        // 3. Fallback: User ASM aktif manapun yang memiliki Chat ID
        if (empty($chatIds)) {
            $anyAsm = User::whereNotNull('telegram_chat_id')
                ->where('telegram_chat_id', '!=', '')
                ->get()
                ->filter(fn (User $u) => $u->isAsm());
            foreach ($anyAsm as $u) {
                $chatIds[] = $u->telegram_chat_id;
            }
        }

        // 4. Fallback ke config .env
        $fallback = config('services.telegram.channel_asm') ?: config('services.telegram.channel_default');
        if (!empty($fallback)) {
            $chatIds[] = $fallback;
        }

        return array_values(array_unique(array_filter($chatIds)));
    }

    /**
     * Dapatkan Chat ID untuk RGM:
     * 1. Atasan ASM (supervisor dari ASM) atau supervisor langsung jika pemohon adalah ASM
     * 2. RGM di wilayah terkait yang memiliki telegram_chat_id
     * 3. Fallback: User RGM manapun yang aktif memiliki telegram_chat_id (misal Pak Ary)
     * 4. Fallback: TELEGRAM_RGM_CHAT_ID atau TELEGRAM_DEFAULT_CHAT_ID (.env)
     */
    public function getRgmChatIds(Claim $claim): array
    {
        $chatIds = [];

        $emp = $claim->effective_employee ?? $claim->employee ?? ($claim->user?->employee_id ? Employee::find($claim->user->employee_id) : null);
        if ($emp) {
            // Jika pemohon adalah ASM -> supervisornya adalah RGM
            if ($emp->isAsm() && $emp->supervisor) {
                $rgmUser = $this->getUserForEmployee($emp->supervisor);
                if ($rgmUser && !empty($rgmUser->telegram_chat_id)) {
                    $chatIds[] = $rgmUser->telegram_chat_id;
                }
            } elseif ($emp->supervisor && $emp->supervisor->supervisor) {
                // Jika pemohon adalah Sales -> supervisor ASM -> supervisornya RGM
                $rgmEmp = $emp->supervisor->supervisor;
                $rgmUser = $this->getUserForEmployee($rgmEmp);
                if ($rgmUser && !empty($rgmUser->telegram_chat_id)) {
                    $chatIds[] = $rgmUser->telegram_chat_id;
                }
            }
        }

        // Cek User role RGM di wilayah tersebut
        $region = $this->getClaimRegion($claim);
        $rgmUsers = User::whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->get()
            ->filter(fn (User $u) => $u->isRgm() && (empty($region) || in_array($region, $u->getRegionList())));

        foreach ($rgmUsers as $u) {
            $chatIds[] = $u->telegram_chat_id;
        }

        // Fallback: Jika tidak ditemukan RGM spesifik wilayah atau atasan, fallback ke RGM aktif manapun yang memiliki chat ID (misal Pak Ary)
        if (empty($chatIds)) {
            $anyRgmUsers = User::whereNotNull('telegram_chat_id')
                ->where('telegram_chat_id', '!=', '')
                ->get()
                ->filter(fn (User $u) => $u->isRgm());
            foreach ($anyRgmUsers as $u) {
                $chatIds[] = $u->telegram_chat_id;
            }
        }

        // Fallback ke config .env
        $fallback = config('services.telegram.channel_rgm') ?: config('services.telegram.channel_default');
        if (!empty($fallback)) {
            $chatIds[] = $fallback;
        }

        return array_values(array_unique(array_filter($chatIds)));
    }

    /**
     * Dapatkan Chat ID untuk Head of Sales (Pak Jejen):
     * 1. User dengan role JEJEN atau nama Jejen yang memiliki telegram_chat_id
     * 2. Fallback: TELEGRAM_JEJEN_CHAT_ID atau TELEGRAM_DEFAULT_CHAT_ID
     */
    public function getJejenChatIds(): array
    {
        $chatIds = [];

        $jejenUsers = User::whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->get()
            ->filter(fn (User $u) => $u->isJejen());

        foreach ($jejenUsers as $u) {
            $chatIds[] = $u->telegram_chat_id;
        }

        $fallback = config('services.telegram.channel_jejen') ?: config('services.telegram.channel_default');
        if (!empty($fallback)) {
            $chatIds[] = $fallback;
        }

        return array_values(array_unique(array_filter($chatIds)));
    }

    /**
     * Dapatkan Chat ID untuk Admin:
     * 1. User role ADMIN yang mengelola region klaim terkait
     * 2. Fallback: User Admin aktif manapun dengan chat ID
     * 3. Fallback: TELEGRAM_ADMIN_CHAT_ID atau TELEGRAM_DEFAULT_CHAT_ID
     */
    public function getAdminChatIds(Claim $claim): array
    {
        $chatIds = [];
        $region = $this->getClaimRegion($claim);

        $adminUsers = User::whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->get()
            ->filter(function (User $u) use ($region) {
                if (!$u->isAdmin()) return false;
                $managed = $u->getManagedRegionsList();
                if (empty($managed)) return true; // Admin tanpa batasan region menerima notifikasi
                return in_array($region, $managed);
            });

        foreach ($adminUsers as $u) {
            $chatIds[] = $u->telegram_chat_id;
        }

        if (empty($chatIds)) {
            $anyAdmin = User::whereNotNull('telegram_chat_id')
                ->where('telegram_chat_id', '!=', '')
                ->get()
                ->filter(fn (User $u) => $u->isAdmin());
            foreach ($anyAdmin as $u) {
                $chatIds[] = $u->telegram_chat_id;
            }
        }

        $fallback = config('services.telegram.channel_admin') ?: config('services.telegram.channel_default');
        if (!empty($fallback)) {
            $chatIds[] = $fallback;
        }

        return array_values(array_unique(array_filter($chatIds)));
    }

    /**
     * Dapatkan Chat ID untuk Finance:
     * 1. User role FINANCE yang memiliki telegram_chat_id
     * 2. Fallback: TELEGRAM_FINANCE_CHAT_ID atau TELEGRAM_DEFAULT_CHAT_ID
     */
    public function getFinanceChatIds(): array
    {
        $chatIds = [];

        $financeUsers = User::whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->get()
            ->filter(fn (User $u) => $u->isFinance());

        foreach ($financeUsers as $u) {
            $chatIds[] = $u->telegram_chat_id;
        }

        $fallback = config('services.telegram.channel_finance') ?: config('services.telegram.channel_default');
        if (!empty($fallback)) {
            $chatIds[] = $fallback;
        }

        return array_values(array_unique(array_filter($chatIds)));
    }

    /**
     * Dapatkan Chat ID Pemohon klaim (untuk notifikasi hasil pencairan / revisi)
     */
    public function getApplicantChatId(Claim $claim): ?string
    {
        if ($claim->user && !empty($claim->user->telegram_chat_id)) {
            return $claim->user->telegram_chat_id;
        }

        $emp = $claim->effective_employee ?? $claim->employee ?? ($claim->user?->employee_id ? Employee::find($claim->user->employee_id) : null);
        if ($emp) {
            $user = $this->getUserForEmployee($emp);
            if ($user && !empty($user->telegram_chat_id)) {
                return $user->telegram_chat_id;
            }
        }

        $fallback = config('services.telegram.channel_default');
        if (!empty($fallback)) {
            return $fallback;
        }

        return null;
    }

    /* =========================================================================
     * FORMATTING & PRESENTATION HELPERS
     * ========================================================================= */

    public function formatRupiah(float|int|string|null $val): string
    {
        return 'Rp ' . number_format((float)$val, 0, ',', '.');
    }

    public function getApplicantName(Claim $claim): string
    {
        return $claim->effective_employee?->name ?? $claim->user?->name ?? 'Pemohon Klaim';
    }

    public function getApplicantRoleLabel(Claim $claim): string
    {
        $emp = $claim->effective_employee;
        $user = $claim->user;

        if ($emp?->isRgm() || $user?->isRgm()) return 'RGM';
        if ($emp?->isAsm() || $user?->isAsm()) return 'ASM';
        return $emp?->position_name ?? $user?->role_name ?? 'Sales Field';
    }

    public function getClaimRegion(Claim $claim): string
    {
        if (!empty($claim->region)) return strtoupper(trim($claim->region));
        if ($claim->effective_employee && !empty($claim->effective_employee->region)) {
            return strtoupper(trim($claim->effective_employee->region));
        }
        if ($claim->user && !empty($claim->user->region)) {
            return strtoupper(trim($claim->user->region));
        }
        return 'CIREBON';
    }

    public function getCategoryLabel(Claim $claim): string
    {
        if ($claim->is_perdin || $claim->claim_category === 'perdin') {
            return 'Perjalanan Dinas (Perdin)';
        }
        if ($claim->claim_category === 'bbm' || str_contains(strtoupper($claim->claim_type_string ?? ''), 'BBM')) {
            return 'BBM Operasional';
        }
        return 'Transport & Entertain Operasional';
    }

    public function getSummaryNotes(Claim $claim): string
    {
        $parts = [];
        if (!empty($claim->purpose_string)) {
            $parts[] = $claim->purpose_string;
        }
        if (!empty($claim->note)) {
            $parts[] = $claim->note;
        }
        if ($claim->is_perdin && !empty($claim->destination_city)) {
            $parts[] = "Tujuan: {$claim->destination_city}";
        }

        $full = implode(' - ', array_filter($parts));
        if (empty($full)) {
            $full = 'Pengajuan klaim operasional lapangan.';
        }

        return htmlspecialchars(mb_strimwidth($full, 0, 150, '...'));
    }

    public function getApprovalUrl(string $routeName): string
    {
        try {
            if (\Illuminate\Support\Facades\Route::has($routeName)) {
                $url = route($routeName);
                if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                    return $url;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        $baseUrl = rtrim(config('app.url') ?: 'http://localhost:8000', '/');
        return "{$baseUrl}/admin";
    }

    protected function safeRoute(string $routeName): ?string
    {
        return $this->getApprovalUrl($routeName);
    }

    protected function createButton(string $label, ?string $url): ?array
    {
        if (empty($url)) {
            return null;
        }

        // Telegram Bot API melarang URL localhost / 127.0.0.1 pada inline keyboard button
        if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1') || str_contains($url, '.local') || str_contains($url, '.test')) {
            return null;
        }

        return [
            [
                [
                    'text' => $label,
                    'url' => $url,
                ]
            ]
        ];
    }
}
