<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class BackupToOneDriveCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:onedrive {--remote=onedrive : Nama remote rclone} {--folder=SalesKlaimBackup : Folder tujuan di OneDrive}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Jalankan backup folder media (storage/app/public) dan database ke Microsoft OneDrive via rclone';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('====================================================');
        $this->info('  AUTO BACKUP STORAGE MEDIA & DATABASE KE ONEDRIVE  ');
        $this->info('====================================================');

        $scriptPath = base_path('scripts/backup_to_onedrive.sh');
        if (!file_exists($scriptPath)) {
            $this->error("Script backup tidak ditemukan di: {$scriptPath}");
            return Command::FAILURE;
        }

        $remote = (string) $this->option('remote');
        $folder = (string) $this->option('folder');

        $this->line("Memulai eksekusi script: {$scriptPath}");
        $this->line("Target Remote: {$remote}:{$folder}");

        $process = new Process([
            '/bin/bash',
            $scriptPath,
        ], base_path(), [
            'ONEDRIVE_REMOTE' => $remote,
            'ONEDRIVE_FOLDER' => $folder,
        ]);

        $process->setTimeout(1800); // 30 menit timeout untuk file berukuran besar

        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if ($process->isSuccessful()) {
            $this->info("\n✅ Proses backup berhasil diselesaikan!");
            return Command::SUCCESS;
        }

        $this->error("\n❌ Terjadi kesalahan saat proses backup!");
        return Command::FAILURE;
    }
}
