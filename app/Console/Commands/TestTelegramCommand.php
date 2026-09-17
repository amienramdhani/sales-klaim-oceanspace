<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Console\Command;

class TestTelegramCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:test {chat_id? : Target Chat ID (Opsional)} {--message= : Custom pesan teks yang ingin dikirim}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Uji coba koneksi Bot Telegram dan pengiriman notifikasi';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram): int
    {
        $this->info('=============================================');
        $this->info('    UJI KONEKSI & NOTIFIKASI TELEGRAM BOT    ');
        $this->info('=============================================');

        if (!$telegram->isEnabled()) {
            $this->error('❌ TELEGRAM_BOT_TOKEN belum diatur di file .env.');
            $this->line('Silakan buat bot melalui @BotFather di Telegram lalu tambahkan token ke .env:');
            $this->line('TELEGRAM_BOT_TOKEN=123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ');
            return Command::FAILURE;
        }

        $this->line('Memeriksa status bot ke Telegram API...');
        $botInfo = $telegram->getMe();

        if ($botInfo) {
            $this->info('✅ Bot Terhubung Berhasil!');
            $this->table(
                ['Key', 'Value'],
                [
                    ['Bot ID', $botInfo['id'] ?? '-'],
                    ['Nama Bot', $botInfo['first_name'] ?? '-'],
                    ['Username', '@' . ($botInfo['username'] ?? '-')],
                    ['Bisa Masuk Grup', ($botInfo['can_join_groups'] ?? false) ? 'Ya' : 'Tidak'],
                ]
            );
        } else {
            $this->warn('⚠️ Tidak dapat mengambil info bot. Pastikan koneksi internet aktif dan TELEGRAM_BOT_TOKEN valid.');
        }

        $targetChatId = $this->argument('chat_id');
        $customMessage = $this->option('message');

        // Jika tidak ada chat_id spesifik yang diinput
        if (empty($targetChatId)) {
            $this->newLine();
            $this->info('📋 Daftar Chat ID Terdaftar di Sistem:');

            $envChannels = [
                ['Role ASM (.env)', config('services.telegram.channel_asm') ?: '-'],
                ['Role RGM (.env)', config('services.telegram.channel_rgm') ?: '-'],
                ['Head of Sales Pak Jejen (.env)', config('services.telegram.channel_jejen') ?: '-'],
                ['Role Admin (.env)', config('services.telegram.channel_admin') ?: '-'],
                ['Role Finance (.env)', config('services.telegram.channel_finance') ?: '-'],
                ['Default Channel (.env)', config('services.telegram.channel_default') ?: '-'],
            ];

            $userChannels = User::whereNotNull('telegram_chat_id')
                ->where('telegram_chat_id', '!=', '')
                ->get(['name', 'role_id', 'telegram_chat_id'])
                ->map(fn ($u) => ["User: {$u->name} ({$u->role_name})", $u->telegram_chat_id])
                ->toArray();

            $allRows = array_merge($envChannels, $userChannels);
            $this->table(['Sumber / Pemilik', 'Chat ID'], $allRows);

            $this->line('Untuk mengirim pesan uji coba ke chat ID tertentu, jalankan:');
            $this->info('php artisan telegram:test <CHAT_ID>');
            return Command::SUCCESS;
        }

        // Jika chat_id diberikan, kirim pesan uji coba
        $this->newLine();
        $this->info("Mengirim pesan tes ke Chat ID: [{$targetChatId}]...");

        $text = $customMessage ?: "🔔 <b>TES KONEKSI BOT TELEGRAM</b>\n"
              . "━━━━━━━━━━━━━━━━━━━━━\n"
              . "Pesan ini dikirim dari server aplikasi <b>Sistem Klaim MSI</b>.\n"
              . "Status: <b>Koneksi Berhasil!</b> ✅\n"
              . "Waktu: " . date('d/m/Y H:i:s') . "\n"
              . "━━━━━━━━━━━━━━━━━━━━━\n"
              . "<i>Bot siap mengirimkan notifikasi pengajuan klaim berjenjang.</i>";

        $success = $telegram->sendMessage($targetChatId, $text);

        if ($success) {
            $this->info("🎉 Pesan berhasil terkirim ke [{$targetChatId}]!");
            return Command::SUCCESS;
        } else {
            $this->error("❌ Gagal mengirim pesan ke [{$targetChatId}].");
            $this->line('Tips:');
            $this->line('1. Jika akun personal: User wajib klik /start terlebih dahulu ke bot.');
            $this->line('2. Jika grup: Pastikan bot telah di-invite ke dalam grup.');
            $this->line('3. Periksa kembali token bot dan koneksi internet.');
            return Command::FAILURE;
        }
    }
}
