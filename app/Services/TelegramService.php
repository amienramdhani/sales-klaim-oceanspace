<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    protected ?string $token;
    protected string $apiUrl;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}";
    }

    /**
     * Periksa apakah bot telegram telah dikonfigurasi
     */
    public function isEnabled(): bool
    {
        return !empty($this->token);
    }

    /**
     * Dapatkan Bot Token saat ini
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * Kirim pesan format HTML ke chat ID Telegram tertentu
     *
     * @param string|int $chatId User Chat ID atau Group ID (bisa diawali tanda minus)
     * @param string $htmlText Teks pesan dalam format HTML
     * @param array|null $inlineButtons Format array inline keyboard button Telegram: [[['text' => 'Label', 'url' => 'https://...']]]
     */
    public function sendMessage(string|int $chatId, string $htmlText, ?array $inlineButtons = null): bool
    {
        if (!$this->isEnabled()) {
            Log::info("TelegramService: Token belum dikonfigurasi di .env (TELEGRAM_BOT_TOKEN kosong). Pesan ke [{$chatId}] dilewati.");
            return false;
        }

        $chatId = trim((string)$chatId);
        if (empty($chatId)) {
            Log::warning("TelegramService: Chat ID kosong. Pengiriman dilewati.");
            return false;
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $htmlText,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];

        if (!empty($inlineButtons)) {
            $payload['reply_markup'] = [
                'inline_keyboard' => $inlineButtons,
            ];
        }

        // Coba kirim dengan timeout yang toleran terhadap jaringan ISP lokal (dengan auto-retry & IPv4)
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::withOptions([
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                    ]
                ])->timeout(6)
                    ->connectTimeout(4)
                    ->post("{$this->apiUrl}/sendMessage", $payload);

                if ($response->successful()) {
                    Log::info("TelegramService: Notifikasi berhasil dikirim ke Chat ID [{$chatId}].");
                    return true;
                }

                // Jika gagal karena Telegram menolak tombol (misal URL localhost pada development), kirim ulang tanpa inline button
                if ($response->status() === 400 && !empty($payload['reply_markup'])) {
                    Log::warning("TelegramService: Gagal dengan tombol pada Chat ID [{$chatId}]. Mencoba kirim ulang tanpa inline button...");
                    unset($payload['reply_markup']);
                    $retry = Http::withOptions([
                        'curl' => [
                            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                        ]
                    ])->timeout(6)
                        ->connectTimeout(4)
                        ->post("{$this->apiUrl}/sendMessage", $payload);

                    if ($retry->successful()) {
                        Log::info("TelegramService: Notifikasi berhasil dikirim ke Chat ID [{$chatId}] (fallback tanpa inline button).");
                        return true;
                    }
                }

                Log::error("TelegramService: Gagal mengirim pesan ke Chat ID [{$chatId}]. Status: " . $response->status() . " Body: " . $response->body());
                return false;
            } catch (\Throwable $e) {
                Log::warning("TelegramService Percobaan #{$attempt} gagal ke [{$chatId}]: " . $e->getMessage());
                if ($attempt < 2) {
                    usleep(500000); // jeda 0.5 detik
                }
            }
        }

        Log::error("TelegramService: Gagal total mengirim ke Chat ID [{$chatId}] setelah 2 percobaan.");
        return false;
    }

    /**
     * Kirim pesan ke banyak penerima (array of chat IDs)
     *
     * @param array $chatIds Daftar Chat ID unik
     * @param string $htmlText Teks pesan HTML
     * @param array|null $inlineButtons Array inline button
     * @return array Ringkasan hasil ['success' => int, 'failed' => int]
     */
    public function sendToMultiple(array $chatIds, string $htmlText, ?array $inlineButtons = null): array
    {
        $uniqueChatIds = array_values(array_unique(array_filter(array_map('trim', $chatIds))));
        $results = ['success' => 0, 'failed' => 0];

        foreach ($uniqueChatIds as $chatId) {
            if ($this->sendMessage($chatId, $htmlText, $inlineButtons)) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Dapatkan informasi bot (nama, username) dari Telegram API
     */
    public function getMe(): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $response = Http::timeout(5)->get("{$this->apiUrl}/getMe");
            if ($response->successful() && ($response->json('ok') === true)) {
                return $response->json('result');
            }
            return null;
        } catch (\Throwable $e) {
            Log::error("TelegramService getMe Exception: " . $e->getMessage());
            return null;
        }
    }
}
