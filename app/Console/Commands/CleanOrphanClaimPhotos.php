<?php

namespace App\Console\Commands;

use App\Models\Claim;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanOrphanClaimPhotos extends Command
{
    protected $signature = 'claims:clean-orphan-photos {--dry-run : Only show files to be deleted without deleting}';

    protected $description = 'Clean up orphan stitched pair photos and duplicate unreferenced images from storage';

    public function handle(): int
    {
        $this->info('Scanning database for active combined photo references...');

        $referenced = [];
        foreach (Claim::all() as $claim) {
            if ($claim->bbm_photo_combined) {
                $referenced[trim($claim->bbm_photo_combined, '/\\')] = true;
            }
            if ($claim->service_photo_combined) {
                $referenced[trim($claim->service_photo_combined, '/\\')] = true;
            }
            if (is_array($claim->items)) {
                foreach ($claim->items as $it) {
                    if (!empty($it['bbm_photo_combined'])) {
                        $referenced[trim($it['bbm_photo_combined'], '/\\')] = true;
                    }
                }
            }
        }

        $this->info('Active referenced combined photos in DB: ' . count($referenced));

        $disk = Storage::disk('public');
        $directories = ['claim-photos', 'claim-service-photos'];
        $dryRun = $this->option('dry-run');

        $deletedCount = 0;
        $deletedBytes = 0;

        foreach ($directories as $dir) {
            $allFiles = $disk->files($dir);
            foreach ($allFiles as $file) {
                $norm = trim($file, '/\\');
                $filename = basename($norm);

                // Target auto-generated bbm_pair and service_pair composite files
                if (str_starts_with($filename, 'bbm_pair_') || str_starts_with($filename, 'service_pair_')) {
                    if (!isset($referenced[$norm])) {
                        $size = $disk->size($norm);
                        $deletedBytes += $size;
                        $deletedCount++;

                        if (!$dryRun) {
                            $disk->delete($norm);
                        }
                    }
                }
            }
        }

        $mb = round($deletedBytes / 1024 / 1024, 2);

        if ($dryRun) {
            $this->warn("[DRY RUN] Would delete {$deletedCount} orphan files, saving {$mb} MB.");
        } else {
            $this->info("Successfully cleaned up {$deletedCount} orphan duplicate files!");
            $this->info("Freed up {$mb} MB of storage space.");
        }

        return Command::SUCCESS;
    }
}
