<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Claim extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'region',
        '_uid',
        'claim_category',
        'employee_id',
        'claim_period_id',
        'claim_date',
        'claim_type',
        'purpose',
        'entertain_subtype',
        'vehicle_type',
        'fuel_type',
        'fuel_pertalite_price',
        'fuel_pertamax_price',
        'fuel_liters',
        'fuel_start_km',
        'fuel_base_amount',
        'fuel_extra_amount',
        'note',
        'amount',
        'items',
        'branch_id',
        'brand',
        'reffnote',
        'city',
        'photos',
        'bbm_photo_before',
        'bbm_photo_after',
        'bbm_photo_combined',
        'is_perdin',
        'homebase',
        'destination_city',
        'distance_km',
        'days_count',
        'nights_count',
        'meal_allowance',
        'lodging_allowance',
        'toll_cost',
        'fuel_cost',
        'car_rental_cost',
        'service_cost',
        'approval_status',
        'approved_by_asm_id',
        'approved_by_asm_at',
        'approved_by_rgm_id',
        'approved_by_rgm_at',
        'approved_by_jejen_id',
        'approved_by_jejen_at',
        'admin_approved_by_id',
        'admin_approved_at',
        'rejection_reason',
        'disbursement_status',
        'disbursed_at',
        'transfer_proof_photo',
        'finance_approved_by_id',
        'finance_approved_at',
        'ba_phone',
        'ba_division',
        'ba_approver_1',
        'ba_approver_2',
        'ba_approver_3',
        'ba_wa_proof_photo',
        'return_transfer_proof',
        'return_transfer_amount',
        'return_transferred_at',
        'return_transfer_by_id',
        'return_transfer_notes',
        'perdin_return_date',
        'admin_gform_submitted',
        'admin_gform_url',
        'admin_gform_submitted_at',
        'nota_balik_submitted',
        'nota_balik_submitted_at',
        'nota_balik_recap_notes',
        'nota_balik_finance_sent',
        'nota_balik_finance_sent_at',
        'kwitansi_number',
        'service_photo_before',
        'service_photo_after',
        'service_photo_combined',
        'company',
        'badan_usaha',
        'actual_fuel_cost',
        'actual_toll_cost',
        'actual_car_rental_cost',
        'actual_service_cost',
        'actual_meal_allowance',
        'actual_amount_total',
        'remaining_amount_calculated',
        'nota_balik_status',
        'nota_balik_rejection_reason',
        'approved_by_yoga_id',
        'approved_by_yoga_at',
        'approved_by_nurbaiti_id',
        'approved_by_nurbaiti_at',
        'approved_by_thia_id',
        'approved_by_thia_at',
        'travel_cost',
        'actual_travel_cost',
        'other_transport_cost',
        'actual_other_transport_cost',
        'custom_bank_name',
        'custom_bank_account_number',
        'custom_bank_account_name',
        'approval_note',
        'asm_approval_note',
        'rgm_approval_note',
        'admin_approval_note',
        'jejen_approval_note',
        'yoga_approval_note',
        'nurbaiti_approval_note',
        'thia_approval_note',
    ];

    protected $casts = [
        'claim_date' => 'date',
        'perdin_return_date' => 'date',
        'amount' => 'decimal:2',
        'fuel_base_amount' => 'decimal:2',
        'fuel_extra_amount' => 'decimal:2',
        'return_transfer_amount' => 'decimal:2',
        'return_transferred_at' => 'datetime',
        'actual_fuel_cost' => 'decimal:2',
        'actual_toll_cost' => 'decimal:2',
        'actual_car_rental_cost' => 'decimal:2',
        'actual_service_cost' => 'decimal:2',
        'actual_meal_allowance' => 'decimal:2',
        'actual_travel_cost' => 'decimal:2',
        'actual_other_transport_cost' => 'decimal:2',
        'travel_cost' => 'decimal:2',
        'other_transport_cost' => 'decimal:2',
        'actual_amount_total' => 'decimal:2',
        'remaining_amount_calculated' => 'decimal:2',
        'approved_by_yoga_at' => 'datetime',
        'approved_by_nurbaiti_at' => 'datetime',
        'approved_by_thia_at' => 'datetime',
        'admin_gform_submitted' => 'boolean',
        'admin_gform_submitted_at' => 'datetime',
        'nota_balik_submitted' => 'boolean',
        'nota_balik_submitted_at' => 'datetime',
        'nota_balik_finance_sent' => 'boolean',
        'nota_balik_finance_sent_at' => 'datetime',
        'fuel_pertalite_price' => 'decimal:2',
        'fuel_pertamax_price' => 'decimal:2',
        'fuel_liters' => 'decimal:3',
        'fuel_start_km' => 'decimal:2',
        'items' => 'array',
        'photos' => 'array',
        'claim_type' => 'array',
        'purpose' => 'array',
        'is_perdin' => 'boolean',
        'distance_km' => 'decimal:2',
        'days_count' => 'integer',
        'nights_count' => 'integer',
        'meal_allowance' => 'decimal:2',
        'lodging_allowance' => 'decimal:2',
        'toll_cost' => 'decimal:2',
        'fuel_cost' => 'decimal:2',
        'car_rental_cost' => 'decimal:2',
        'service_cost' => 'decimal:2',
        'approved_by_asm_at' => 'datetime',
        'approved_by_rgm_at' => 'datetime',
        'approved_by_jejen_at' => 'datetime',
        'admin_approved_at' => 'datetime',
        'finance_approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
    ];

    public function getEffectiveBankName(): string
    {
        return !empty($this->custom_bank_name)
            ? $this->custom_bank_name
            : ($this->effective_employee?->bank_name ?? '-');
    }

    public function getEffectiveBankNameAttribute(): string
    {
        return $this->getEffectiveBankName();
    }

    public function getEffectiveBankAccountNumber(): string
    {
        return !empty($this->custom_bank_account_number)
            ? $this->custom_bank_account_number
            : ($this->effective_employee?->bank_account_number ?? '-');
    }

    public function getEffectiveBankAccountNumberAttribute(): string
    {
        return $this->getEffectiveBankAccountNumber();
    }

    public function getEffectiveBankAccountName(): string
    {
        return !empty($this->custom_bank_account_name)
            ? $this->custom_bank_account_name
            : ($this->effective_employee?->bank_account_name ?? ($this->effective_employee?->name ?? '-'));
    }

    public function getEffectiveBankAccountNameAttribute(): string
    {
        return $this->getEffectiveBankAccountName();
    }

    public static function generateUid(?string $badanUsaha = null): string
    {
        $bu = strtoupper(trim((string)$badanUsaha));
        $prefix = (str_contains($bu, 'MEDIA SELULAR') || str_contains($bu, 'MSI')) ? 'MSI-' : 'UID-';

        do {
            $digits = str_pad((string)random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
            $candidate = $prefix . $digits;
        } while (static::where('_uid', $candidate)->exists());

        return $candidate;
    }

    public function isTopSelular(): bool
    {
        $bu = strtoupper(trim((string)($this->badan_usaha ?? $this->company ?? '')));
        return str_contains($bu, 'TOP') || str_contains($bu, 'COMPLETE') || str_contains($bu, 'CV');
    }

    public function isMediaSelularIndonesia(): bool
    {
        $bu = strtoupper(trim((string)($this->badan_usaha ?? $this->company ?? '')));
        if (empty($bu)) {
            return true;
        }
        return str_contains($bu, 'MEDIA SELULAR') || str_contains($bu, 'MSI') || (!str_contains($bu, 'TOP') && !str_contains($bu, 'COMPLETE'));
    }

    public function getApprovalStageLabelAttribute(): string
    {
        return match ($this->approval_status) {
            'DRAFT' => 'Draft (Belum Diajukan)',
            'DIAJUKAN' => ($this->effective_employee?->isAsm() || $this->user?->isAsm()) 
                ? 'Menunggu Approval RGM' 
                : (($this->effective_employee?->isRgm() || $this->user?->isRgm()) ? 'Menunggu Verifikasi Admin' : 'Menunggu Approval ASM'),
            'ACC_ASM' => 'Menunggu Approval RGM',
            'ACC_RGM' => 'Menunggu Verifikasi Admin',
            'ACC_ADMIN' => 'Menunggu Approval Head of Sales (Pak Jejen)',
            'ACC_PAK_JEJEN' => $this->isTopSelular() 
                ? 'Menunggu Approval Manajer Keuangan (Thia Aisyah)' 
                : 'Menunggu Approval Manager Finance (Yoga P. Hadi)',
            'WAITING_YOGA' => 'Menunggu Approval Manager Finance (Yoga P. Hadi)',
            'WAITING_NURBAITI', 'ACC_YOGA' => 'Menunggu Approval Finance Coordinator (Nurbaeti Riaseha)',
            'WAITING_THIA' => 'Menunggu Approval Manajer Keuangan (Thia Aisyah)',
            'ACC_NURBAITI' => 'Menunggu Pencairan Finance PT Media Selular Indonesia',
            'ACC_THIA' => 'Menunggu Pencairan Finance CV Top Selular',
            'DISETUJUI' => $this->disbursement_status === 'Sudah Dicairkan' 
                ? 'Selesai Dicairkan' 
                : ($this->isTopSelular() 
                    ? 'Disetujui (Antrean Pencairan Finance CV Top Selular)' 
                    : 'Disetujui (Antrean Pencairan Finance PT Media Selular Indonesia)'),
            'SEDANG_DIREVISI' => 'Perlu Revisi Pemohon',
            'DITOLAK_FINANCE' => 'Ditolak Finance',
            'DITOLAK' => 'Ditolak',
            default => $this->approval_status ?? '-',
        };
    }

    public function getApprovalJourneySummaryAttribute(): ?string
    {
        if (!empty($this->rejection_reason)) {
            return "Alasan Tolak: {$this->rejection_reason}";
        }
        $stages = [];
        if ($this->approved_by_asm_at) $stages[] = 'ASM: ' . ($this->asmApprover?->name ?? 'OK');
        if ($this->approved_by_rgm_at) $stages[] = 'RGM: ' . ($this->rgmApprover?->name ?? 'OK');
        if ($this->admin_approved_at) $stages[] = 'Admin: ' . ($this->adminApprover?->name ?? 'OK');
        if ($this->approved_by_jejen_at) $stages[] = 'Head of Sales (Jejen): OK';
        if ($this->approved_by_yoga_at) $stages[] = 'Mgr Finance (Yoga P. Hadi): OK';
        if ($this->approved_by_nurbaiti_at) $stages[] = 'Finance Coord (Nurbaeti Riaseha): OK';
        if ($this->approved_by_thia_at) $stages[] = 'Mgr Keuangan (Thia Aisyah): OK';
        if ($this->disbursed_at) $stages[] = 'Dicairkan: ' . $this->disbursed_at->format('d/m/Y');

        return !empty($stages) ? implode(' → ', $stages) : null;
    }

    protected static function booted(): void
    {
        static::creating(function (Claim $claim) {
            if (empty($claim->user_id) && auth()->check()) {
                $claim->user_id = auth()->id();
            }
            if (empty($claim->employee_id) && auth()->check() && auth()->user()->employee_id) {
                $claim->employee_id = auth()->user()->employee_id;
            }

            // Sync Badan Usaha & Company
            $submitter = auth()->user() ?? ($claim->user_id ? User::find($claim->user_id) : null);
            if (empty($claim->badan_usaha) && $submitter) {
                $claim->badan_usaha = $submitter->badan_usaha ?? 'PT MEDIA SELULAR INDONESIA';
            }
            if (empty($claim->company)) {
                $claim->company = $claim->badan_usaha;
            }

            if (empty($claim->_uid)) {
                $claim->_uid = static::generateUid($claim->badan_usaha);
            }
            if (empty($claim->claim_date)) {
                $claim->claim_date = now()->toDateString();
            }
            $applicantEmp = $claim->effective_employee ?? ($claim->employee_id ? Employee::find($claim->employee_id) : null);
            $isAsmApplicant = $applicantEmp?->isAsm() || $submitter?->isAsm();
            $isRgmApplicant = $applicantEmp?->isRgm() || $submitter?->isRgm();

            if (empty($claim->approval_status) || in_array($claim->approval_status, ['DRAFT', 'DIAJUKAN'])) {
                if ($isAsmApplicant) {
                    $claim->approval_status = 'ACC_ASM';
                    $claim->approved_by_asm_id = $claim->approved_by_asm_id ?? ($claim->user_id ?? auth()->id());
                    $claim->approved_by_asm_at = $claim->approved_by_asm_at ?? now();
                } elseif ($isRgmApplicant) {
                    $claim->approval_status = 'ACC_RGM';
                    $claim->approved_by_rgm_id = $claim->approved_by_rgm_id ?? ($claim->user_id ?? auth()->id());
                    $claim->approved_by_rgm_at = $claim->approved_by_rgm_at ?? now();
                } else {
                    $claim->approval_status = 'DIAJUKAN';
                }
            }

            // Validasi pembatasan role Sales: hanya boleh mengajukan Perdin dan Service Motor
            $submitter = auth()->user() ?? ($claim->user_id ? User::find($claim->user_id) : null);
            if ($submitter && $submitter->isSales() && !$submitter->isAdmin() && !$submitter->isSuperAdmin()) {
                $category = $claim->claim_category;
                $isPerdin = (bool)$claim->is_perdin;
                $isService = in_array($category, ['service', 'service_motor']);
                if (!$isPerdin && !$isService && $category !== 'perdin') {
                    throw new \InvalidArgumentException('Karyawan Sales hanya diizinkan mengajukan Klaim Perjalanan Dinas (Perdin) dan Klaim Service Motor.');
                }
            }
        });

        static::saving(function (Claim $claim) {
            // Auto sync employee_id from period if available
            if (!$claim->employee_id && $claim->claimPeriod?->employee_id) {
                $claim->employee_id = $claim->claimPeriod->employee_id;
            }

            if (is_null($claim->meal_allowance)) $claim->meal_allowance = 0;
            if (is_null($claim->lodging_allowance)) $claim->lodging_allowance = 0;
            if (is_null($claim->toll_cost)) $claim->toll_cost = 0;
            if (is_null($claim->fuel_cost)) $claim->fuel_cost = 0;
            if (is_null($claim->car_rental_cost)) $claim->car_rental_cost = 0;
            if (is_null($claim->service_cost)) $claim->service_cost = 0;
            if (is_null($claim->days_count)) $claim->days_count = 1;
            if (is_null($claim->nights_count)) $claim->nights_count = 0;

            // If Perjalanan Dinas is enabled, calculate amount from perdin breakdown
            if ($claim->is_perdin || $claim->claim_category === 'perdin') {
                $claim->is_perdin = true;
                $claim->claim_category = 'perdin';

                $calcAmount = (float)$claim->meal_allowance
                    + (float)$claim->lodging_allowance
                    + (float)$claim->fuel_cost
                    + (float)$claim->travel_cost
                    + (float)$claim->car_rental_cost
                    + (float)($claim->other_transport_cost ?: $claim->toll_cost)
                    + (float)$claim->service_cost;

                if ($calcAmount > 0 || empty($claim->amount) || (float)$claim->amount == 0) {
                    $claim->amount = $calcAmount;
                }

                if (empty($claim->claim_type)) {
                    $claim->claim_type = ['Perjalanan Dinas'];
                }
                if (empty($claim->purpose)) {
                    $claim->purpose = ["Perjalanan Dinas ke " . ($claim->destination_city ?: 'Tujuan')];
                }
                if (empty($claim->note)) {
                    $claim->note = "Homebase: " . ($claim->homebase ?: 'Purwokerto') . " - " . ($claim->distance_km ?? 80) . " KM";
                }
            } elseif (is_array($claim->items) && count($claim->items) > 0) {
                // Auto calculate total amount from items if items repeater is filled
                $totalFromItems = 0;
                $types = [];
                $purposes = [];
                $notes = [];
                $cities = [];

                foreach ($claim->items as $item) {
                    $totalFromItems += (float) ($item['amount'] ?? 0);
                    $itemName = $item['item_name'] ?? ($item['name'] ?? ($item['claim_type'] ?? null));
                    if (!empty($itemName)) {
                        $types[] = $itemName;
                    }
                    if (!empty($item['purpose'])) {
                        $purposes[] = $item['purpose'];
                    } elseif (!empty($itemName)) {
                        $purposes[] = $itemName;
                    }
                    if (!empty($item['note'])) {
                        $notes[] = $item['note'];
                    }
                    if (!empty($item['city'])) {
                        $cities[] = $item['city'];
                    }
                }

                if ($totalFromItems > 0 || empty($claim->amount) || (float)$claim->amount == 0) {
                    $claim->amount = $totalFromItems;
                }

                if ($claim->claim_category === 'service' || $claim->claim_category === 'service_motor') {
                    $claim->claim_type = ['Service Motor'];
                    if (!empty($types)) {
                        $claim->purpose = array_values(array_unique(array_merge($types, $purposes)));
                    }
                } else {
                    $claim->claim_type = array_values(array_unique($types));
                    $claim->purpose = array_values(array_unique($purposes));
                }

                if (!empty($notes)) {
                    $claim->note = implode(' | ', array_filter($notes));
                }
                if (!empty($cities)) {
                    $claim->city = implode(', ', array_values(array_unique($cities)));
                } elseif (!empty($claim->city)) {
                    $updatedItems = $claim->items;
                    $itemChanged = false;
                    foreach ($updatedItems as $idx => $it) {
                        if (empty($it['city'])) {
                            $updatedItems[$idx]['city'] = $claim->city;
                            $itemChanged = true;
                        }
                    }
                    if ($itemChanged) {
                        $claim->items = $updatedItems;
                    }
                }
            }

            // Stitch BBM Before & After photos into 1 side-by-side composite photo
            $claim->combineBbmPhotos();

            // Stitch Motor Service Before & After photos into 1 side-by-side composite photo
            $claim->combineServicePhotos();
        });

        static::saved(function (Claim $claim) {
            $claim->claimPeriod?->recalculateTotals();
        });

        static::deleting(function (Claim $claim) {
            if (is_array($claim->photos)) {
                foreach ($claim->photos as $photo) {
                    if ($photo && Storage::disk('public')->exists($photo)) {
                        Storage::disk('public')->delete($photo);
                    }
                }
            }
            if ($claim->bbm_photo_before && Storage::disk('public')->exists($claim->bbm_photo_before)) {
                Storage::disk('public')->delete($claim->bbm_photo_before);
            }
            if ($claim->bbm_photo_after && Storage::disk('public')->exists($claim->bbm_photo_after)) {
                Storage::disk('public')->delete($claim->bbm_photo_after);
            }
            if ($claim->bbm_photo_combined && Storage::disk('public')->exists($claim->bbm_photo_combined)) {
                Storage::disk('public')->delete($claim->bbm_photo_combined);
            }
            if ($claim->service_photo_before && Storage::disk('public')->exists($claim->service_photo_before)) {
                Storage::disk('public')->delete($claim->service_photo_before);
            }
            if ($claim->service_photo_after && Storage::disk('public')->exists($claim->service_photo_after)) {
                Storage::disk('public')->delete($claim->service_photo_after);
            }
            if ($claim->service_photo_combined && Storage::disk('public')->exists($claim->service_photo_combined)) {
                Storage::disk('public')->delete($claim->service_photo_combined);
            }
            if (is_array($claim->items)) {
                foreach ($claim->items as $it) {
                    foreach (['bbm_photo_before', 'bbm_photo_after', 'bbm_photo_combined'] as $key) {
                        if (!empty($it[$key]) && Storage::disk('public')->exists($it[$key])) {
                            Storage::disk('public')->delete($it[$key]);
                        }
                    }
                    if (!empty($it['photos']) && is_array($it['photos'])) {
                        foreach ($it['photos'] as $p) {
                            if ($p && Storage::disk('public')->exists($p)) {
                                Storage::disk('public')->delete($p);
                            }
                        }
                    }
                }
            }
        });

        static::deleted(function (Claim $claim) {
            $claim->claimPeriod?->recalculateTotals();
        });
    }

    /**
     * Creates a high-definition side-by-side combined image for a single Before & After pair with smart compression
     */
    public function createPairCombinedImage(?string $beforeRelPath, ?string $afterRelPath, string $label = 'Pengisian BBM', int $idx = 1, ?string $oldRelPath = null): ?string
    {
        @ini_set('memory_limit', '512M');

        $beforePath = $beforeRelPath ? Storage::disk('public')->path($beforeRelPath) : null;
        $afterPath = $afterRelPath ? Storage::disk('public')->path($afterRelPath) : null;

        $img1 = ($beforePath && file_exists($beforePath)) ? $this->createImageResource($beforePath) : null;
        $img2 = ($afterPath && file_exists($afterPath)) ? $this->createImageResource($afterPath) : null;

        if (!$img1 && !$img2) {
            return null;
        }

        $w1 = $img1 ? imagesx($img1) : 1280;
        $h1 = $img1 ? imagesy($img1) : 960;
        $w2 = $img2 ? imagesx($img2) : 1280;
        $h2 = $img2 ? imagesy($img2) : 960;

        // Target height optimal (Full HD 1080px) agar angka odometer & detail struk tetap sangat tajam & jelas
        $maxSourceHeight = max($h1, $h2);
        $targetHeight = min($maxSourceHeight, 1080);
        $bannerHeight = max(42, (int)($targetHeight * 0.05));
        $gap = 16;

        $newW1 = (int)(($targetHeight / $h1) * $w1);
        $newW2 = (int)(($targetHeight / $h2) * $w2);

        $rowWidth = $newW1 + $newW2 + $gap;
        $rowHeight = $targetHeight + $bannerHeight;

        $rowCanvas = imagecreatetruecolor($rowWidth, $rowHeight);
        $bgColor = imagecolorallocate($rowCanvas, 248, 250, 252);
        $bannerColor = imagecolorallocate($rowCanvas, 30, 41, 59);
        $textColor = imagecolorallocate($rowCanvas, 255, 255, 255);

        imagefilledrectangle($rowCanvas, 0, 0, $rowWidth, $rowHeight, $bgColor);
        imagefilledrectangle($rowCanvas, 0, 0, $rowWidth, $bannerHeight, $bannerColor);

        $cleanLabel = trim($label);
        $isService = stripos($cleanLabel, 'service') !== false;
        $title1 = $isService ? strtoupper($cleanLabel) . " — SEBELUM (BEFORE)" : "TRANSAKSI #{$idx} — SEBELUM (BEFORE)";
        $title2 = $isService ? strtoupper($cleanLabel) . " — SESUDAH (AFTER)" : "TRANSAKSI #{$idx} — SESUDAH (AFTER)";

        $font = 5;
        $textY = max(8, (int)(($bannerHeight - 16) / 2));
        imagestring($rowCanvas, $font, 16, $textY, $title1, $textColor);
        imagestring($rowCanvas, $font, $newW1 + $gap + 16, $textY, $title2, $textColor);

        if ($img1) {
            imagecopyresampled($rowCanvas, $img1, 0, $bannerHeight, 0, 0, $newW1, $targetHeight, $w1, $h1);
            imagedestroy($img1);
        }
        if ($img2) {
            imagecopyresampled($rowCanvas, $img2, $newW1 + $gap, $bannerHeight, 0, 0, $newW2, $targetHeight, $w2, $h2);
            imagedestroy($img2);
        }

        // Hapus file gabungan lama jika ada agar tidak menumpuk di disk
        if ($oldRelPath && Storage::disk('public')->exists($oldRelPath)) {
            Storage::disk('public')->delete($oldRelPath);
        }

        $prefix = $isService ? 'service_pair_' : 'bbm_pair_';
        $combinedRelPath = 'claim-photos/' . $prefix . $idx . '_' . uniqid() . '.jpg';
        $combinedFullPath = Storage::disk('public')->path($combinedRelPath);

        $dir = dirname($combinedFullPath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        // Progressive JPEG kualitas 85% - mempertahankan ketajaman tinggi (angka odometer & teks nota sangat jelas), ukuran file hemat ~80-85% (~200-250KB)
        imageinterlace($rowCanvas, true);
        imagejpeg($rowCanvas, $combinedFullPath, 85);
        imagedestroy($rowCanvas);

        return $combinedRelPath;
    }

    /**
     * Stitches BBM Before & After photos per transaction item with duplicate prevention
     */
    public function combineBbmPhotos(): void
    {
        @ini_set('memory_limit', '512M');

        $updatedItems = $this->items;
        $firstCombined = null;

        if (is_array($updatedItems) && count($updatedItems) > 0) {
            $originalItems = $this->getOriginal('items');
            foreach ($updatedItems as $idx => &$it) {
                $before = $it['bbm_photo_before'] ?? null;
                $after = $it['bbm_photo_after'] ?? null;
                $currentCombined = $it['bbm_photo_combined'] ?? null;
                $label = !empty($it['note']) ? $it['note'] : 'Pengisian #' . ($idx + 1);

                if ($before || $after) {
                    $origBefore = $originalItems[$idx]['bbm_photo_before'] ?? null;
                    $origAfter = $originalItems[$idx]['bbm_photo_after'] ?? null;
                    $photosUnchanged = ($before === $origBefore && $after === $origAfter);

                    // Cek apakah file gabungan sudah ada dan foto tidak berubah
                    if ($photosUnchanged && $currentCombined && Storage::disk('public')->exists($currentCombined)) {
                        if (!$firstCombined) {
                            $firstCombined = $currentCombined;
                        }
                        continue;
                    }

                    $pairPath = $this->createPairCombinedImage($before, $after, $label, $idx + 1, $currentCombined);
                    if ($pairPath) {
                        $it['bbm_photo_combined'] = $pairPath;
                        if (!$firstCombined) {
                            $firstCombined = $pairPath;
                        }
                    }
                }
            }
            unset($it);
            $this->items = $updatedItems;
        } elseif ($this->bbm_photo_before || $this->bbm_photo_after) {
            $currentCombined = $this->bbm_photo_combined;
            $photosUnchanged = ($this->bbm_photo_before === $this->getOriginal('bbm_photo_before') && $this->bbm_photo_after === $this->getOriginal('bbm_photo_after'));

            if ($photosUnchanged && $currentCombined && Storage::disk('public')->exists($currentCombined)) {
                $firstCombined = $currentCombined;
            } else {
                $pairPath = $this->createPairCombinedImage($this->bbm_photo_before, $this->bbm_photo_after, 'Pengisian BBM', 1, $currentCombined);
                if ($pairPath) {
                    $firstCombined = $pairPath;
                }
            }
        }

        if ($firstCombined) {
            $this->bbm_photo_combined = $firstCombined;
        }
    }

    /**
     * Stitches Motor Service Before & After photos into 1 side-by-side composite photo with duplicate prevention
     */
    public function combineServicePhotos(): void
    {
        if ($this->service_photo_before || $this->service_photo_after) {
            $currentCombined = $this->service_photo_combined;
            $photosUnchanged = ($this->service_photo_before === $this->getOriginal('service_photo_before') && $this->service_photo_after === $this->getOriginal('service_photo_after'));

            if ($photosUnchanged && $currentCombined && Storage::disk('public')->exists($currentCombined)) {
                return; // Sudah ada dan valid di disk, tidak perlu generate ulang
            }
            $label = !empty($this->note) ? $this->note : 'Service Motor';
            $combined = $this->createPairCombinedImage($this->service_photo_before, $this->service_photo_after, $label, 1, $currentCombined);
            if ($combined) {
                $this->service_photo_combined = $combined;
            }
        }
    }

    /**
     * Indonesian terbilang helper (number to words)
     */
    public static function terbilang(float|int $angka): string
    {
        $angka = abs((int)$angka);
        $baca = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];

        if ($angka < 12) {
            $hasil = ' ' . $baca[$angka];
        } elseif ($angka < 20) {
            $hasil = self::terbilang($angka - 10) . ' Belas';
        } elseif ($angka < 100) {
            $hasil = self::terbilang((int)($angka / 10)) . ' Puluh ' . self::terbilang($angka % 10);
        } elseif ($angka < 200) {
            $hasil = ' Seratus ' . self::terbilang($angka - 100);
        } elseif ($angka < 1000) {
            $hasil = self::terbilang((int)($angka / 100)) . ' Ratus ' . self::terbilang($angka % 100);
        } elseif ($angka < 2000) {
            $hasil = ' Seribu ' . self::terbilang($angka - 1000);
        } elseif ($angka < 1000000) {
            $hasil = self::terbilang((int)($angka / 1000)) . ' Ribu ' . self::terbilang($angka % 1000);
        } elseif ($angka < 1000000000) {
            $hasil = self::terbilang((int)($angka / 1000000)) . ' Juta ' . self::terbilang($angka % 1000000);
        } elseif ($angka < 1000000000000) {
            $hasil = self::terbilang((int)($angka / 1000000000)) . ' Miliar ' . self::terbilang(fmod($angka, 1000000000));
        } else {
            $hasil = '';
        }

        return trim(preg_replace('/\s+/', ' ', $hasil));
    }

    public function getTerbilangAmountAttribute(): string
    {
        $val = (float)$this->amount;
        if ($val <= 0) {
            return 'Nol';
        }
        return self::terbilang($val);
    }

    public function getTerbilangMealAllowanceAttribute(): string
    {
        $val = (float)($this->meal_allowance ?? 0);
        if ($val <= 0) {
            return 'Nol';
        }
        return self::terbilang($val);
    }

    public function getPerdinReturnDeadlineAttribute(): ?\Carbon\Carbon
    {
        if (!$this->is_perdin && $this->claim_category !== 'perdin') {
            return null;
        }
        $endDate = $this->perdin_return_date ? \Carbon\Carbon::parse($this->perdin_return_date) : ($this->claim_date ? $this->claim_date->copy()->addDays(max(1, (int)$this->days_count) - 1) : null);
        if (!$endDate) return null;
        return $endDate->copy()->addDay();
    }

    /**
     * Get all combined pair photos (1 composite image per transaction: Before + After side by side)
     */
    public function getBbmPairCombinedPhotos(): array
    {
        $pairs = [];

        if (is_array($this->items) && count($this->items) > 0) {
            foreach ($this->items as $idx => $it) {
                $before = $it['bbm_photo_before'] ?? null;
                $after = $it['bbm_photo_after'] ?? null;
                $combined = $it['bbm_photo_combined'] ?? null;
                $spbuLabel = !empty($it['note']) ? $it['note'] : 'Pengisian #' . ($idx + 1);
                $tglLabel = !empty($it['receipt_date']) ? date('d/m/Y', strtotime($it['receipt_date'])) : '-';
                $label = 'TRANSAKSI #' . ($idx + 1) . ' — ' . strtoupper($spbuLabel) . ' (' . $tglLabel . ')';

                $beforePath = ($before && Storage::disk('public')->exists($before)) ? Storage::disk('public')->path($before) : null;
                $afterPath = ($after && Storage::disk('public')->exists($after)) ? Storage::disk('public')->path($after) : null;
                $combinedPath = ($combined && Storage::disk('public')->exists($combined)) ? Storage::disk('public')->path($combined) : null;

                if (!$combinedPath && ($before || $after)) {
                    $generated = $this->createPairCombinedImage($before, $after, $spbuLabel, $idx + 1);
                    if ($generated && Storage::disk('public')->exists($generated)) {
                        $combinedPath = Storage::disk('public')->path($generated);
                        $combined = $generated;
                    }
                }

                if ($combinedPath || $beforePath || $afterPath) {
                    $pairs[] = [
                        'label' => $label,
                        'combined_path' => $combinedPath,
                        'combined_rel' => $combined,
                        'before_path' => $beforePath,
                        'before_rel' => $before,
                        'after_path' => $afterPath,
                        'after_rel' => $after,
                        'item_index' => $idx + 1,
                        'spbu' => $spbuLabel,
                        'date' => $tglLabel,
                        'amount' => (float)($it['amount'] ?? 0),
                        'km' => $it['fuel_start_km'] ?? null,
                    ];
                }
            }
        }

        if (empty($pairs) && ($this->bbm_photo_before || $this->bbm_photo_after || $this->bbm_photo_combined)) {
            $before = $this->bbm_photo_before;
            $after = $this->bbm_photo_after;
            $combined = $this->bbm_photo_combined;

            $beforePath = ($before && Storage::disk('public')->exists($before)) ? Storage::disk('public')->path($before) : null;
            $afterPath = ($after && Storage::disk('public')->exists($after)) ? Storage::disk('public')->path($after) : null;
            $combinedPath = ($combined && Storage::disk('public')->exists($combined)) ? Storage::disk('public')->path($combined) : null;

            if (!$combinedPath && ($before || $after)) {
                $generated = $this->createPairCombinedImage($before, $after, 'Pengisian BBM', 1);
                if ($generated && Storage::disk('public')->exists($generated)) {
                    $combinedPath = Storage::disk('public')->path($generated);
                    $combined = $generated;
                }
            }

            if ($combinedPath || $beforePath || $afterPath) {
                $pairs[] = [
                    'label' => 'TRANSAKSI #1 — PENGISIAN BBM',
                    'combined_path' => $combinedPath,
                    'combined_rel' => $combined,
                    'before_path' => $beforePath,
                    'before_rel' => $before,
                    'after_path' => $afterPath,
                    'after_rel' => $after,
                    'item_index' => 1,
                    'spbu' => $this->note ?: 'Pengisian BBM',
                    'date' => $this->claim_date ? $this->claim_date->format('d/m/Y') : '-',
                    'amount' => (float)$this->amount,
                    'km' => $this->fuel_start_km,
                ];
            }
        }

        return $pairs;
    }

    protected function createImageResource(string $path)
    {
        $info = @getimagesize($path);
        if (!$info) return null;

        $img = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            default => null,
        };

        if ($img && $info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3:
                        $img = imagerotate($img, 180, 0);
                        break;
                    case 6:
                        $img = imagerotate($img, -90, 0);
                        break;
                    case 8:
                        $img = imagerotate($img, 90, 0);
                        break;
                }
            }
        }

        return $img;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function effective_employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function claimPeriod(): BelongsTo
    {
        return $this->belongsTo(ClaimPeriod::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function attendanceDoc(): HasOne
    {
        return $this->hasOne(AttendanceDoc::class);
    }

    public function asmApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_asm_id');
    }

    public function rgmApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_rgm_id');
    }

    public function adminApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_approved_by_id');
    }

    public function jejenApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_jejen_id');
    }

    public function yogaApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_yoga_id');
    }

    public function nurbaitiApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_nurbaiti_id');
    }

    public function thiaApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_thia_id');
    }

    public function financeApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_approved_by_id');
    }

    /**
     * Check if this claim has attendance data (either via attendanceDoc or items repeater)
     */
    public function hasAttendanceData(): bool
    {
        if ($this->attendanceDoc()->exists()) {
            return true;
        }

        if (is_array($this->items)) {
            foreach ($this->items as $it) {
                if (!empty($it['attendance_purpose']) || !empty($it['attendance_participants'])) {
                    return true;
                }
                if (($it['claim_type'] ?? '') === 'Entertain' && in_array($it['entertain_subtype'] ?? '', ['Makan', 'Ulang Tahun'])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Build an in-memory AttendanceDoc instance from claim data without persisting to DB
     */
    public function buildAttendanceDocInstance(): ?AttendanceDoc
    {
        if ($this->relationLoaded('attendanceDoc') && $this->attendanceDoc) {
            return $this->attendanceDoc;
        }

        $entertainItem = null;
        if (is_array($this->items)) {
            foreach ($this->items as $it) {
                if (!empty($it['attendance_participants']) || !empty($it['attendance_purpose'])) {
                    $entertainItem = $it;
                    break;
                }
                if (($it['claim_type'] ?? '') === 'Entertain' && in_array($it['entertain_subtype'] ?? '', ['Makan', 'Ulang Tahun'])) {
                    $entertainItem = $it;
                    break;
                }
            }
        }

        if (!$entertainItem && !$this->hasAttendanceData()) {
            return $this->attendanceDoc;
        }

        $purpose = !empty($entertainItem['attendance_purpose']) 
            ? $entertainItem['attendance_purpose'] 
            : (!empty($entertainItem['purpose']) ? $entertainItem['purpose'] : ($this->purpose_string ?: 'Jamuan Makan & Pertemuan Tim'));
        $place = !empty($entertainItem['attendance_place']) 
            ? $entertainItem['attendance_place'] 
            : (!empty($entertainItem['note']) ? $entertainItem['note'] : ($this->city ?: 'Purwokerto'));
        $date = !empty($entertainItem['attendance_date']) 
            ? \Carbon\Carbon::parse($entertainItem['attendance_date']) 
            : (!empty($entertainItem['receipt_date']) ? \Carbon\Carbon::parse($entertainItem['receipt_date']) : ($this->claim_date ?: now()));
        $notes = $entertainItem['attendance_notes'] ?? null;
        $subtype = $entertainItem['entertain_subtype'] ?? 'Makan';
        $category = $subtype === 'Ulang Tahun' ? 'Ulang Tahun' : 'Makan';

        $doc = new AttendanceDoc([
            'claim_id' => $this->id,
            'user_id' => $this->user_id,
            'employee_id' => $this->effective_employee_id ?? $this->employee_id,
            'claim_period_id' => $this->claim_period_id,
            'category' => $category,
            'purpose' => $purpose,
            'place' => $place,
            'date' => $date,
            'notes' => $notes,
            'photos' => $entertainItem['attendance_photos'] ?? ($this->photos ?? []),
            'receipt_photos' => $this->photos ?? [],
            'estimated_amount' => (float)$this->amount,
            'admin_status' => 'SUDAH_DISESUAIKAN',
        ]);

        $participantsCollection = collect();
        $participants = $entertainItem['attendance_participants'] ?? [];
        if (is_array($participants)) {
            foreach ($participants as $p) {
                if (!empty($p['name'])) {
                    $part = new \App\Models\AttendancePart([
                        'name' => $p['name'],
                        'position' => $p['position'] ?? null,
                        'signature_info' => $p['status'] ?? 'HADIR',
                    ]);
                    $participantsCollection->push($part);
                }
            }
        }
        $doc->setRelation('participants', $participantsCollection);

        return $doc;
    }

    /**
     * Sync or get the AttendanceDoc instance for this claim
     */
    public function syncOrGetAttendanceDoc(): ?AttendanceDoc
    {
        $doc = $this->attendanceDoc;

        // If items contain attendance info, create or sync
        if (is_array($this->items)) {
            $entertainItem = null;
            foreach ($this->items as $it) {
                if (!empty($it['attendance_participants']) || !empty($it['attendance_purpose'])) {
                    $entertainItem = $it;
                    break;
                }
                if (($it['claim_type'] ?? '') === 'Entertain' && in_array($it['entertain_subtype'] ?? '', ['Makan', 'Ulang Tahun'])) {
                    $entertainItem = $it;
                    break;
                }
            }

            if ($entertainItem) {
                $purpose = !empty($entertainItem['attendance_purpose']) 
                    ? $entertainItem['attendance_purpose'] 
                    : (!empty($entertainItem['purpose']) ? $entertainItem['purpose'] : ($this->purpose_string ?: 'Jamuan Makan & Pertemuan Tim'));
                $place = !empty($entertainItem['attendance_place']) 
                    ? $entertainItem['attendance_place'] 
                    : (!empty($entertainItem['note']) ? $entertainItem['note'] : ($this->city ?: 'Purwokerto'));
                $date = !empty($entertainItem['attendance_date']) 
                    ? $entertainItem['attendance_date'] 
                    : (!empty($entertainItem['receipt_date']) ? $entertainItem['receipt_date'] : ($this->claim_date ?: now()));
                $notes = $entertainItem['attendance_notes'] ?? null;

                $subtype = $entertainItem['entertain_subtype'] ?? 'Makan';
                $category = $subtype === 'Ulang Tahun' ? 'Ulang Tahun' : 'Makan';
                $adminStatus = in_array($this->approval_status, ['ACC_ADMIN', 'DISETUJUI']) ? 'SUDAH_DISESUAIKAN' : 'MENUNGGU_PENYESUAIAN';

                if (!$doc) {
                    $doc = AttendanceDoc::create([
                        'claim_id' => $this->id,
                        'user_id' => $this->user_id,
                        'employee_id' => $this->effective_employee_id ?? $this->employee_id,
                        'claim_period_id' => $this->claim_period_id,
                        'category' => $category,
                        'purpose' => $purpose,
                        'place' => $place,
                        'date' => $date,
                        'notes' => $notes,
                        'photos' => $entertainItem['attendance_photos'] ?? ($this->photos ?? []),
                        'receipt_photos' => $this->photos ?? [],
                        'estimated_amount' => (float)$this->amount,
                        'admin_status' => $adminStatus,
                    ]);
                } else {
                    $doc->update([
                        'category' => $category,
                        'purpose' => $purpose,
                        'place' => $place,
                        'date' => $date,
                        'notes' => $notes,
                        'photos' => $entertainItem['attendance_photos'] ?? ($doc->photos ?? $this->photos),
                        'receipt_photos' => $this->photos ?: $doc->receipt_photos,
                        'estimated_amount' => (float)$this->amount ?: $doc->estimated_amount,
                        'admin_status' => $adminStatus,
                    ]);
                }

                // Sync participants
                $participants = $entertainItem['attendance_participants'] ?? [];
                if (is_array($participants) && count($participants) > 0) {
                    $doc->participants()->delete();
                    foreach ($participants as $p) {
                        if (!empty($p['name'])) {
                            $doc->participants()->create([
                                'name' => $p['name'],
                                'position' => $p['position'] ?? null,
                                'signature_info' => $p['status'] ?? 'HADIR',
                            ]);
                        }
                    }
                }

                return $doc->fresh(['participants']);
            }
        }

        return $doc;
    }

    public function approvedByAsm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_asm_id');
    }

    public function approvedByRgm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_rgm_id');
    }

    public function approvedByJejen(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_jejen_id');
    }

    public function adminApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_approved_by_id');
    }

    public function financeApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finance_approved_by_id');
    }

    /**
     * Cek apakah user adalah ASM atasan langsung dari pemohon klaim
     */
    public static function isAsmSupervisorOf(?User $user, Claim $record): bool
    {
        if (!$user) return false;

        $applicantEmp = $record->effective_employee;
        // Jika pemohon adalah ASM atau RGM, tidak perlu dan tidak boleh di-ACC oleh ASM (langsung ke RGM / Admin)
        if ($applicantEmp) {
            if ($applicantEmp->isAsm() || $applicantEmp->isRgm()) return false;
        } elseif ($record->user?->isAsm() || $record->user?->isRgm()) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isAdmin()) return true;
        if (!$user->isAsm()) return false;

        if (!$applicantEmp) return false;

        $asmEmpId = $user->getEffectiveEmployeeId();
        if ($asmEmpId && $applicantEmp->supervisor_id == $asmEmpId) {
            return true;
        }

        // Fallback: region
        if (!$applicantEmp->supervisor_id) {
            $userRegions = $user->getRegionList();
            if ($applicantEmp->region && in_array(strtoupper($applicantEmp->region), $userRegions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek apakah user adalah RGM atasan dari pemohon (atasan dari ASM atau atasan Sales)
     */
    public static function isRgmSupervisorOf(?User $user, Claim $record): bool
    {
        if (!$user) return false;
        if ($user->isSuperAdmin() || $user->isAdmin()) return true;
        if (!$user->isRgm()) return false;

        $applicantEmp = $record->effective_employee;
        if (!$applicantEmp) return false;

        // Jika pemohon adalah RGM, RGM tidak bisa meng-ACC diri sendiri
        if ($applicantEmp->isRgm()) return false;

        $rgmEmpId = $user->getEffectiveEmployeeId();
        // 1. Jika pemohon adalah ASM di bawah RGM ini
        if ($rgmEmpId && $applicantEmp->supervisor_id == $rgmEmpId) {
            return true;
        }

        // 2. Jika pemohon adalah Sales yang ASM-nya di bawah RGM ini
        if ($rgmEmpId && $applicantEmp->supervisor?->supervisor_id == $rgmEmpId) {
            return true;
        }

        // Fallback: region
        $userRegions = $user->getRegionList();
        if ($applicantEmp->region && in_array(strtoupper($applicantEmp->region), $userRegions)) {
            return true;
        }

        return false;
    }

    /**
     * Cek apakah user berhak melakukan persetujuan level Pak Jejen (Head of Sales)
     */
    public static function isJejenApproverOf(?User $user, Claim $record): bool
    {
        if (!$user) return false;
        return $user->isJejen() || $user->isSuperAdmin() || $user->isAdmin();
    }

    /**
     * Scope a query to only include claims visible to the given user.
     *
     * Rules:
     * 1. Super Admin: sees all claims.
     * 2. Finance: only sees claims ready for disbursement (DISETUJUI), in revision (DITOLAK_FINANCE), or disbursed.
     * 3. Admin: sees claims they inputted OR matching their managed regions.
     * 4. ASM: sees their own claims + subordinates' claims.
     * 5. RGM: sees their own claims + ASM subordinates' claims + Sales under ASM.
     * 6. Pak Jejen: sees their own claims + claims pending their approval / approved.
     * 7. Field users (Sales): only see their own claims.
     */
    public function scopeVisibleToUser(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return $query;
        }

        if ($user->isFinance()) {
            return $query->where(function (Builder $q) {
                $q->whereIn('approval_status', ['DISETUJUI', 'DITOLAK_FINANCE'])
                  ->orWhere('disbursement_status', 'Sudah Dicairkan');
            });
        }


        $empId = $user->getEffectiveEmployeeId();

        if ($user->isAsm()) {
            return $query->where(function (Builder $q) use ($user, $empId) {
                $q->where('user_id', $user->id);
                if ($empId) {
                    $q->orWhere('employee_id', $empId)
                      ->orWhereHas('employee', fn ($eq) => $eq->where('supervisor_id', $empId));
                }
                $regions = $user->getRegionList();
                if (!empty($regions)) {
                    $q->orWhereHas('employee', fn ($eq) => $eq->whereIn('region', $regions));
                }
            });
        }

        if ($user->isRgm()) {
            return $query->where(function (Builder $q) use ($user, $empId) {
                $q->where('user_id', $user->id);
                if ($empId) {
                    $q->orWhere('employee_id', $empId)
                      ->orWhereHas('employee', fn ($eq) => $eq->where('supervisor_id', $empId))
                      ->orWhereHas('employee.supervisor', fn ($sq) => $sq->where('supervisor_id', $empId));
                }
                $regions = $user->getRegionList();
                if (!empty($regions)) {
                    $q->orWhereHas('employee', fn ($eq) => $eq->whereIn('region', $regions));
                }
            });
        }

        if ($user->isJejen()) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('approved_by_jejen_id', $user->id)
                  ->orWhereIn('approval_status', ['ACC_RGM', 'ACC_PAK_JEJEN', 'WAITING_YOGA', 'WAITING_NURBAITI', 'WAITING_THIA', 'DISETUJUI'])
                  ->orWhere(function ($sub) {
                      $sub->where('approval_status', 'DIAJUKAN')
                          ->whereHas('employee', function ($eq) {
                              $eq->whereHas('positionModel', fn ($pq) => $pq->where('name', 'like', '%RGM%'))
                                 ->orWhereHas('role', fn ($rq) => $rq->where('name', 'like', '%RGM%')->orWhere('code', 'like', '%RGM%'))
                                 ->orWhere('position', 'like', '%RGM%');
                          });
                  });
            });
        }

        if ($user->isYoga()) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('approved_by_yoga_id', $user->id)
                  ->orWhereIn('approval_status', ['WAITING_YOGA', 'WAITING_NURBAITI', 'ACC_YOGA', 'DISETUJUI']);
            });
        }

        if ($user->isNurbaiti()) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('approved_by_nurbaiti_id', $user->id)
                  ->orWhereIn('approval_status', ['WAITING_NURBAITI', 'DISETUJUI']);
            });
        }

        if ($user->isThia()) {
            return $query->where(function (Builder $q) use ($user) {
                $q->where('user_id', $user->id)
                  ->orWhere('approved_by_thia_id', $user->id)
                  ->orWhereIn('approval_status', ['WAITING_THIA', 'DISETUJUI']);
            });
        }

        // Field users: only see their own claims
        return $query->where(function (Builder $q) use ($user, $empId) {
            $q->where('user_id', $user->id);
            if ($empId) {
                $q->orWhere('employee_id', $empId);
            }
        });
    }

    /**
     * Scope: Hanya pengajuan milik user yang sedang login (pribadi)
     */
    public function scopeOwnSubmissionsOnly(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        $empId = $user->getEffectiveEmployeeId();
        return $query->where(function (Builder $q) use ($user, $empId) {
            $q->where('user_id', $user->id);
            if ($empId) {
                $q->orWhere('employee_id', $empId);
            }
        });
    }

    /**
     * Scope: Klaim bawahan yang menunggu ACC dari ASM
     */
    public function scopePendingAsmApproval(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        $query->where('approval_status', 'DIAJUKAN');

        // Exclude self-claims
        $empId = $user->getEffectiveEmployeeId();
        $query->where('user_id', '!=', $user->id);
        if ($empId) {
            $query->where('employee_id', '!=', $empId);
        }

        // Applicant must be subordinate (not ASM, not RGM)
        $query->whereHas('employee', function ($eq) {
            $eq->whereDoesntHave('role', fn ($rq) => $rq->where('code', 'like', '%ASM%')->orWhere('code', 'like', '%RGM%'))
               ->whereDoesntHave('positionModel', fn ($pq) => $pq->where('name', 'like', '%ASM%')->orWhere('name', 'like', '%RGM%'));
        });

        if ($user->isSuperAdmin()) {
            return $query;
        }

        // Must be under this ASM
        return $query->where(function (Builder $q) use ($user, $empId) {
            if ($empId) {
                $q->whereHas('employee', fn ($eq) => $eq->where('supervisor_id', $empId));
            }
            $regions = $user->getRegionList();
            if (!empty($regions)) {
                $q->orWhereHas('employee', fn ($eq) => $eq->whereIn('region', $regions));
            }
        });
    }

    /**
     * Scope: Klaim bawahan yang menunggu ACC dari RGM
     */
    public function scopePendingRgmApproval(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // Exclude self-claims
        $empId = $user->getEffectiveEmployeeId();
        $query->where('user_id', '!=', $user->id);
        if ($empId) {
            $query->where('employee_id', '!=', $empId);
        }

        // Waiting for RGM:
        // Case A: Sales claim that already got ACC_ASM
        // Case B: ASM claim with DIAJUKAN (skips ASM)
        $query->where(function (Builder $q) {
            $q->where('approval_status', 'ACC_ASM')
              ->orWhere(function ($sub) {
                  $sub->where('approval_status', 'DIAJUKAN')
                      ->whereHas('employee', function ($eq) {
                          $eq->whereHas('role', fn ($rq) => $rq->where('code', 'like', '%ASM%')->orWhere('name', 'like', '%ASM%'))
                             ->orWhereHas('positionModel', fn ($pq) => $pq->where('name', 'like', '%ASM%'))
                             ->orWhere('position', 'like', '%ASM%');
                      });
              });
        });

        if ($user->isSuperAdmin()) {
            return $query;
        }

        // Must be under this RGM's supervision or region
        return $query->where(function (Builder $q) use ($user, $empId) {
            if ($empId) {
                $q->whereHas('employee', fn ($eq) => $eq->where('supervisor_id', $empId))
                  ->orWhereHas('employee.supervisor', fn ($sq) => $sq->where('supervisor_id', $empId));
            }
            $regions = $user->getRegionList();
            if (!empty($regions)) {
                $q->orWhereIn('claims.region', $regions)
                  ->orWhereHas('employee', fn ($eq) => $eq->whereIn('region', $regions));
            }
        });
    }

    /**
     * Scope: Klaim yang menunggu ACC dari Head of Sales (Pak Jejen)
     * Alur Baru: Pak Jejen menerima klaim yang telah diverifikasi oleh Admin (ACC_ADMIN)
     */
    public function scopePendingJejenApproval(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();
        if ($user) {
            $empId = $user->getEffectiveEmployeeId();
            $query->where('user_id', '!=', $user->id);
            if ($empId) {
                $query->where('employee_id', '!=', $empId);
            }
        }

        return $query->where('approval_status', 'ACC_ADMIN');
    }

    /**
     * Scope: Klaim yang menunggu ACC dari Manager Finance (Pak Yoga) - PT Media Selular Indonesia
     */
    public function scopePendingYogaApproval(Builder $query, ?User $user = null): Builder
    {
        return $query->where('approval_status', 'WAITING_YOGA');
    }

    /**
     * Scope: Klaim yang menunggu ACC dari Finance Coordinator (Bu Nurbaiti) - PT Media Selular Indonesia
     */
    public function scopePendingNurbaitiApproval(Builder $query, ?User $user = null): Builder
    {
        return $query->where('approval_status', 'WAITING_NURBAITI');
    }

    /**
     * Scope: Klaim yang menunggu ACC dari Manajer Keuangan (Thia Aisyah) - CV Top Selular
     */
    public function scopePendingThiaApproval(Builder $query, ?User $user = null): Builder
    {
        return $query->where('approval_status', 'WAITING_THIA');
    }

    /**
     * Scope: Klaim yang menunggu verifikasi dari Admin
     * Alur Resmi Ketat: Sales -> ASM -> RGM -> Admin -> Head of Sales -> Finance
     * Admin HANYA menerima:
     * 1. Klaim yang telah disetujui RGM (ACC_RGM)
     * 2. Klaim yang diajukan langsung oleh RGM (DIAJUKAN dengan pemohon RGM)
     * 3. Klaim hasil revisi yang dikembalikan oleh Finance (DITOLAK_FINANCE)
     */
    public function scopePendingAdminVerification(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        $query->where(function (Builder $q) {
            $q->whereIn('approval_status', ['ACC_RGM', 'DITOLAK_FINANCE'])
              ->orWhere(function ($sub) {
                  $sub->where('approval_status', 'DIAJUKAN')
                      ->whereHas('employee', function ($eq) {
                          $eq->whereHas('role', fn ($rq) => $rq->where('code', 'like', '%RGM%')->orWhere('name', 'like', '%RGM%'))
                             ->orWhereHas('positionModel', fn ($pq) => $pq->where('name', 'like', '%RGM%'))
                             ->orWhere('position', 'like', '%RGM%');
                      });
              });
        });

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return $query;
        }

        $managedRegions = $user->getManagedRegionsList();
        if (empty($managedRegions)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($managedRegions) {
            foreach ($managedRegions as $region) {
                $q->orWhereRaw('UPPER(claims.region) LIKE ?', ['%' . strtoupper($region) . '%'])
                  ->orWhereRaw('UPPER(claims.homebase) LIKE ?', ['%' . strtoupper($region) . '%'])
                  ->orWhereRaw('UPPER(claims.city) LIKE ?', ['%' . strtoupper($region) . '%']);
            }
            $q->orWhereHas('employee', function (Builder $eq) use ($managedRegions) {
                foreach ($managedRegions as $region) {
                    $eq->orWhereRaw('UPPER(employees.region) LIKE ?', ['%' . strtoupper($region) . '%'])
                       ->orWhereRaw('UPPER(employees.homebase) LIKE ?', ['%' . strtoupper($region) . '%']);
                }
            });
        });
    }

    /**
     * Scope: Klaim yang menunggu pencairan dari Finance
     */
    public function scopePendingFinanceDisbursement(Builder $query): Builder
    {
        return $query->where('approval_status', 'DISETUJUI')
                     ->where('disbursement_status', '!=', 'Sudah Dicairkan');
    }

    /**
     * Scope: Klaim BBM yang menunggu pencairan / nota balik dari Finance
     */
    public function scopePendingFinanceBbmDisbursement(Builder $query): Builder
    {
        return $query->where('approval_status', 'DISETUJUI')
                     ->where('disbursement_status', '!=', 'Sudah Dicairkan')
                     ->where(function (Builder $q) {
                         $q->where('claim_category', 'bbm')
                           ->orWhere('claim_type', 'like', '%BBM%');
                     });
    }

    /**
     * Scope: Klaim Non-BBM yang menunggu pencairan langsung dari Finance
     */
    public function scopePendingFinanceNonBbmDisbursement(Builder $query): Builder
    {
        return $query->where('approval_status', 'DISETUJUI')
                     ->where('disbursement_status', '!=', 'Sudah Dicairkan')
                     ->where(function (Builder $q) {
                         $q->whereNull('claim_category')
                           ->orWhere('claim_category', '!=', 'bbm');
                     })
                     ->where(function (Builder $q) {
                         $q->whereNull('claim_type')
                           ->orWhere('claim_type', 'not like', '%BBM%');
                     });
    }

    /**
     * Scope: Klaim yang sudah di-ACC Admin dan sedang menunggu approval atasan eksekutif
     */
    public function scopeApprovedByAdminWaitingExecutive(Builder $query): Builder
    {
        return $query->whereIn('approval_status', [
            'ACC_ADMIN',
            'ACC_PAK_JEJEN',
            'WAITING_YOGA',
            'WAITING_THIA',
            'WAITING_NURBAITI',
        ]);
    }

    /**
     * Scope: Klaim yang dapat dilihat oleh Finance (sudah di-ACC Admin ke atas atau sudah dicairkan)
     */
    public function scopeVisibleToFinance(Builder $query, ?User $user = null): Builder
    {
        $query->where(function (Builder $q) {
            $q->whereIn('approval_status', [
                'ACC_ADMIN',
                'ACC_PAK_JEJEN',
                'WAITING_YOGA',
                'WAITING_THIA',
                'WAITING_NURBAITI',
                'DISETUJUI',
                'DITOLAK_FINANCE',
            ])
            ->orWhere('disbursement_status', 'Sudah Dicairkan');
        });

        if ($user) {
            if ($user->isFinanceTop() || $user->isFinanceCvcs()) {
                $query->where(function (Builder $q) {
                    $q->whereRaw("UPPER(COALESCE(badan_usaha, company, '')) LIKE '%TOP%'")
                      ->orWhereRaw("UPPER(COALESCE(badan_usaha, company, '')) LIKE '%COMPLETE%'")
                      ->orWhereRaw("UPPER(COALESCE(badan_usaha, company, '')) LIKE '%CV%'");
                });
            } elseif ($user->isFinanceMsi()) {
                $query->where(function (Builder $q) {
                    $q->whereRaw("UPPER(COALESCE(badan_usaha, company, '')) LIKE '%MEDIA SELULAR%'")
                      ->orWhereRaw("UPPER(COALESCE(badan_usaha, company, '')) LIKE '%MSI%'")
                      ->orWhere(function ($sub) {
                          $sub->whereRaw("UPPER(COALESCE(badan_usaha, company, '')) NOT LIKE '%TOP%'")
                              ->whereRaw("UPPER(COALESCE(badan_usaha, company, '')) NOT LIKE '%COMPLETE%'");
                      });
                });
            } elseif (!empty($user->badan_usaha)) {
                $bu = strtoupper(trim($user->badan_usaha));
                $query->where(function (Builder $q) use ($bu) {
                    $q->whereRaw('UPPER(badan_usaha) LIKE ?', ["%{$bu}%"])
                      ->orWhereRaw('UPPER(company) LIKE ?', ["%{$bu}%"]);
                });
            }
        }

        return $query;
    }

    /**
     * Get array of all line items (for Excel rows)
     */
    public function getLineItems(): array
    {
        if ($this->is_perdin || $this->claim_category === 'perdin') {
            $items = [];
            if ((float)$this->meal_allowance > 0) {
                $items[] = [
                    'claim_type' => 'Perjalanan Dinas (Uang Makan)',
                    'purpose' => "Uang Makan {$this->days_count} Hari",
                    'note' => "Tujuan: {$this->destination_city} ({$this->distance_km} km)",
                    'city' => $this->destination_city ?: $this->city,
                    'amount' => (float)$this->meal_allowance,
                ];
            }
            if ((float)$this->lodging_allowance > 0) {
                $items[] = [
                    'claim_type' => 'Perjalanan Dinas (Penginapan)',
                    'purpose' => "Uang Penginapan {$this->nights_count} Malam",
                    'note' => "Akomodasi Hotel/Penginapan {$this->destination_city}",
                    'city' => $this->destination_city ?: $this->city,
                    'amount' => (float)$this->lodging_allowance,
                ];
            }
            if ((float)$this->toll_cost > 0) {
                $items[] = [
                    'claim_type' => 'Perjalanan Dinas (Tol)',
                    'purpose' => 'Biaya Tol Operasional',
                    'note' => 'Struk Pembayaran Tol',
                    'city' => $this->destination_city ?: $this->city,
                    'amount' => (float)$this->toll_cost,
                ];
            }
            if ((float)$this->fuel_cost > 0) {
                $items[] = [
                    'claim_type' => 'Perjalanan Dinas (BBM)',
                    'purpose' => 'Biaya Bensin Perjalanan Dinas',
                    'note' => 'Struk Pembelian BBM',
                    'city' => $this->destination_city ?: $this->city,
                    'amount' => (float)$this->fuel_cost,
                ];
            }
            if ((float)$this->car_rental_cost > 0) {
                $items[] = [
                    'claim_type' => 'Kompensasi Sewa Mobil',
                    'purpose' => 'Kompensasi Sewa Kendaraan Operasional',
                    'note' => 'Sewa Mobil Wilayah ' . ($this->destination_city ?: $this->city),
                    'city' => $this->destination_city ?: $this->city,
                    'amount' => (float)$this->car_rental_cost,
                ];
            }
            if ((float)$this->service_cost > 0) {
                $items[] = [
                    'claim_type' => 'Kompensasi Service Kendaraan',
                    'purpose' => 'Service & Ganti Oli Kendaraan Pribadi',
                    'note' => 'Service 3 Bulanan',
                    'city' => $this->destination_city ?: $this->city,
                    'amount' => (float)$this->service_cost,
                ];
            }
            return !empty($items) ? $items : [
                [
                    'claim_type' => 'Perjalanan Dinas',
                    'purpose' => "Perjalanan Dinas ke {$this->destination_city}",
                    'note' => "Jarak: {$this->distance_km} km",
                    'city' => $this->destination_city ?: $this->city,
                    'amount' => (float)$this->amount,
                ]
            ];
        }

        if (is_array($this->items) && count($this->items) > 0) {
            return $this->items;
        }

        return [
            [
                'claim_type' => $this->claim_type_string ?: 'Entertain',
                'purpose' => $this->purpose_string ?: '-',
                'note' => $this->note ?: '-',
                'city' => $this->city ?: '-',
                'amount' => (float)$this->amount,
            ]
        ];
    }

    /**
     * Get all attachments (general photos + bbm combined photo)
     */
    public function getAllPhotoUrls(): array
    {
        $urls = [];
        if ($this->bbm_photo_combined) {
            $urls[] = Storage::disk('public')->url($this->bbm_photo_combined);
        }
        if (is_array($this->photos)) {
            foreach ($this->photos as $photo) {
                $urls[] = Storage::disk('public')->url($photo);
            }
        }
        return array_values(array_unique($urls));
    }

    /**
     * Get formatted string of claim types
     */
    public function getClaimTypeStringAttribute(): string
    {
        if ($this->is_perdin) {
            return 'Perjalanan Dinas';
        }
        if (is_array($this->claim_type)) {
            return implode(', ', $this->claim_type);
        }
        return (string) $this->claim_type;
    }

    /**
     * Get formatted string of purposes
     */
    public function getPurposeStringAttribute(): string
    {
        if ($this->is_perdin) {
            return "Perjalanan Dinas ke {$this->destination_city} ({$this->distance_km} km)";
        }
        if (is_array($this->purpose)) {
            return implode(', ', $this->purpose);
        }
        return (string) $this->purpose;
    }

    /**
     * Get effective employee (direct employee or via period)
     */
    public function getEffectiveEmployeeAttribute(): ?Employee
    {
        return $this->employee ?? $this->claimPeriod?->employee;
    }


    /**
     * Get fuel compliance assessment for Mobil & Pertalite +5k rules and Pertamax conversion
     */
    public function getFuelComplianceAttribute(): array
    {
        $types = is_array($this->claim_type) ? implode(' ', $this->claim_type) : (string)$this->claim_type;
        $isBbm = str_contains(strtoupper($types), 'BBM') || $this->claim_category === 'bbm';

        if (!$isBbm) {
            return ['status' => 'na', 'label' => '-', 'color' => 'gray'];
        }

        if (strtoupper($this->fuel_type ?? '') === 'PERTAMAX') {
            $liters = $this->fuel_liters > 0 ? $this->fuel_liters : ($this->fuel_pertamax_price > 0 ? round((float)$this->fuel_base_amount / (float)$this->fuel_pertamax_price, 2) : 0);
            return [
                'status' => 'info',
                'label' => "Pertamax Konversi Pertalite ({$liters} L)",
                'color' => 'info'
            ];
        }

        if (($this->vehicle_type ?? 'Mobil') === 'Mobil') {
            $base = (float)$this->fuel_base_amount;
            $amt = (float)$this->amount;

            $isPlus5k = ($base > 0 && ($base % 10000 === 5000)) || ($amt > 0 && ($amt % 10000 === 5000)) || ((float)$this->fuel_extra_amount >= 5000);

            if ($isPlus5k) {
                return [
                    'status' => 'valid',
                    'label' => 'Pertalite Mobil (Nota +5k Sesuai)',
                    'color' => 'success'
                ];
            } else {
                return [
                    'status' => 'warning',
                    'label' => 'BBM Bulat (Wajib Berita Acara)',
                    'color' => 'warning'
                ];
            }
        }

        return [
            'status' => 'valid',
            'label' => ($this->fuel_type ?: 'Pertalite') . ' (' . ($this->vehicle_type ?: 'Motor') . ')',
            'color' => 'success'
        ];
    }

    /**
     * Get BBM budget comparison and over budget alert for this claim's employee in this month
     */
    public function getBbmBudgetStatusAttribute(): array
    {
        $emp = $this->effective_employee;
        if (!$emp) {
            return ['budget' => 0, 'used' => (float)$this->amount, 'is_over' => false, 'over_amount' => 0, 'remaining' => 0, 'label' => 'Tanpa Plafon', 'color' => 'gray'];
        }

        $budget = (float)$emp->bbm_budget;
        $month = $this->claim_date ? $this->claim_date->month : null;
        $year = $this->claim_date ? $this->claim_date->year : null;
        $used = $emp->getUsedBbmForPeriod($month, $year);

        if ($budget <= 0) {
            return [
                'budget' => 0,
                'used' => $used,
                'is_over' => false,
                'over_amount' => 0,
                'remaining' => 0,
                'label' => 'Tanpa Plafon',
                'color' => 'gray'
            ];
        }

        if ($used > $budget) {
            $over = $used - $budget;
            return [
                'budget' => $budget,
                'used' => $used,
                'is_over' => true,
                'over_amount' => $over,
                'remaining' => 0,
                'label' => '⚠️ LEWAT BATAS (+Rp ' . number_format($over, 0, ',', '.') . ')',
                'color' => 'danger',
            ];
        }

        $remaining = $budget - $used;
        return [
            'budget' => $budget,
            'used' => $used,
            'is_over' => false,
            'over_amount' => 0,
            'remaining' => $remaining,
            'label' => '✅ Sesuai Plafon (Sisa Rp ' . number_format($remaining, 0, ',', '.') . ')',
            'color' => 'success',
        ];
    }

    /**
     * Get Entertain budget comparison and over budget alert
     */
    public function getEntertainBudgetStatusAttribute(): array
    {
        $emp = $this->effective_employee;
        if (!$emp) {
            return ['budget' => 0, 'used' => (float)$this->amount, 'is_over' => false, 'over_amount' => 0, 'remaining' => 0, 'label' => 'Tanpa Plafon', 'color' => 'gray'];
        }

        $budget = (float)$emp->entertain_budget;
        $month = $this->claim_date ? $this->claim_date->month : null;
        $year = $this->claim_date ? $this->claim_date->year : null;
        $used = $emp->getUsedEntertainForPeriod($month, $year, true);

        if ($budget <= 0) {
            return [
                'budget' => 0,
                'used' => $used,
                'is_over' => false,
                'over_amount' => 0,
                'remaining' => 0,
                'label' => 'Tanpa Plafon',
                'color' => 'gray'
            ];
        }

        if ($used > $budget) {
            $over = $used - $budget;
            return [
                'budget' => $budget,
                'used' => $used,
                'is_over' => true,
                'over_amount' => $over,
                'remaining' => 0,
                'label' => '⚠️ LEWAT BATAS (+Rp ' . number_format($over, 0, ',', '.') . ')',
                'color' => 'danger',
            ];
        }

        $remaining = $budget - $used;
        return [
            'budget' => $budget,
            'used' => $used,
            'is_over' => false,
            'over_amount' => 0,
            'remaining' => $remaining,
            'label' => '✅ Sesuai Plafon (Sisa Rp ' . number_format($remaining, 0, ',', '.') . ')',
            'color' => 'success',
        ];
    }

    /**
     * Get Perdin budget comparison and over budget alert
     */
    public function getPerdinBudgetStatusAttribute(): array
    {
        $emp = $this->effective_employee;
        if (!$emp) {
            return ['budget' => 0, 'used' => (float)$this->amount, 'is_over' => false, 'over_amount' => 0, 'remaining' => 0, 'label' => 'Tanpa Plafon', 'color' => 'gray'];
        }

        $budget = (float)$emp->perdin_budget;
        $month = $this->claim_date ? $this->claim_date->month : null;
        $year = $this->claim_date ? $this->claim_date->year : null;
        $used = $emp->getUsedPerdinForPeriod($month, $year);

        if ($budget <= 0) {
            return [
                'budget' => 0,
                'used' => $used,
                'is_over' => false,
                'over_amount' => 0,
                'remaining' => 0,
                'label' => 'Tanpa Batas Plafon',
                'color' => 'gray'
            ];
        }

        if ($used > $budget) {
            $over = $used - $budget;
            return [
                'budget' => $budget,
                'used' => $used,
                'is_over' => true,
                'over_amount' => $over,
                'remaining' => 0,
                'label' => '⚠️ LEWAT BATAS (+Rp ' . number_format($over, 0, ',', '.') . ')',
                'color' => 'danger',
            ];
        }

        $remaining = $budget - $used;
        return [
            'budget' => $budget,
            'used' => $used,
            'is_over' => false,
            'over_amount' => 0,
            'remaining' => $remaining,
            'label' => '✅ Sesuai Plafon (Sisa Rp ' . number_format($remaining, 0, ',', '.') . ')',
            'color' => 'success',
        ];
    }

    /**
     * Get Transport budget comparison and over budget alert
     */
    public function getTransportBudgetStatusAttribute(): array
    {
        $emp = $this->effective_employee;
        if (!$emp) {
            return ['budget' => 0, 'used' => (float)$this->amount, 'is_over' => false, 'over_amount' => 0, 'remaining' => 0, 'label' => 'Tanpa Plafon', 'color' => 'gray'];
        }

        $budget = (float)$emp->service_motor_budget;
        $month = $this->claim_date ? $this->claim_date->month : null;
        $year = $this->claim_date ? $this->claim_date->year : null;
        $used = $emp->getUsedTransportForPeriod($month, $year);

        if ($budget <= 0) {
            return [
                'budget' => 0,
                'used' => $used,
                'is_over' => false,
                'over_amount' => 0,
                'remaining' => 0,
                'label' => 'Tanpa Batas Plafon',
                'color' => 'gray'
            ];
        }

        if ($used > $budget) {
            $over = $used - $budget;
            return [
                'budget' => $budget,
                'used' => $used,
                'is_over' => true,
                'over_amount' => $over,
                'remaining' => 0,
                'label' => '⚠️ LEWAT BATAS (+Rp ' . number_format($over, 0, ',', '.') . ')',
                'color' => 'danger',
            ];
        }

        $remaining = $budget - $used;
        return [
            'budget' => $budget,
            'used' => $used,
            'is_over' => false,
            'over_amount' => 0,
            'remaining' => $remaining,
            'label' => '✅ Sesuai Plafon (Sisa Rp ' . number_format($remaining, 0, ',', '.') . ')',
            'color' => 'success',
        ];
    }

    public function returnTransferBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'return_transfer_by_id');
    }

    /**
     * Get remaining upfront budget for BBM claims
     */
    public function getRemainingBudgetAmountAttribute(): float
    {
        $budget = (float)($this->effective_employee?->bbm_budget ?? 0);
        $used = (float)$this->amount;
        return max(0, $budget - $used);
    }

    /**
     * Determine if this claim requires a return transfer to Finance
     */
    public function getNeedsReturnTransferAttribute(): bool
    {
        $budget = (float)($this->effective_employee?->bbm_budget ?? 0);
        return $budget > 0 && ((float)$this->amount < $budget);
    }

    /**
     * Get return transfer status info
     */
    public function getReturnTransferStatusAttribute(): array
    {
        $budget = (float)($this->effective_employee?->bbm_budget ?? 0);
        $used = (float)$this->amount;

        if ($budget <= 0) {
            return [
                'status' => 'no_budget',
                'label' => 'Tanpa Plafon',
                'color' => 'gray',
                'diff' => 0,
            ];
        }

        if ($used >= $budget) {
            return [
                'status' => 'no_refund',
                'label' => $used > $budget ? '⚠️ Over Plafon' : 'Selesai (Pas)',
                'color' => $used > $budget ? 'danger' : 'gray',
                'diff' => $used - $budget,
            ];
        }

        $sisa = $budget - $used;

        if (!empty($this->return_transfer_proof)) {
            return [
                'status' => 'transferred',
                'label' => '✅ Sudah Ditransfer Balik (Rp ' . number_format($this->return_transfer_amount ?: $sisa, 0, ',', '.') . ')',
                'color' => 'success',
                'diff' => $sisa,
            ];
        }

        return [
            'status' => 'pending',
            'label' => '⏳ Wajib Transfer Balik Rp ' . number_format($sisa, 0, ',', '.'),
            'color' => 'warning',
            'diff' => $sisa,
        ];
    }

    /**
     * Get all attached files/photos across all fields and line items for this claim
     */
    public function getAllAttachedFiles(): array
    {
        $files = [];
        $seen = [];

        $addFile = function (?string $path, string $category, string $title, ?string $meta = null) use (&$files, &$seen) {
            if (empty($path)) return;
            $normalized = trim($path, '/\\');
            if (empty($normalized) || isset($seen[$normalized])) return;
            $seen[$normalized] = true;

            $ext = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg']);
            $isPdf = ($ext === 'pdf');
            $exists = Storage::disk('public')->exists($normalized);
            $sizeFormatted = null;
            if ($exists) {
                try {
                    $bytes = Storage::disk('public')->size($normalized);
                    $sizeFormatted = $bytes >= 1048576 
                        ? number_format($bytes / 1048576, 1) . ' MB' 
                        : number_format($bytes / 1024, 0) . ' KB';
                } catch (\Throwable $e) {
                    $sizeFormatted = null;
                }
            }

            $files[] = [
                'path' => $normalized,
                'url' => Storage::disk('public')->url($normalized),
                'category' => $category,
                'title' => $title,
                'meta' => $meta,
                'filename' => basename($normalized),
                'ext' => $ext,
                'is_image' => $isImage,
                'is_pdf' => $isPdf,
                'exists' => $exists,
                'size' => $sizeFormatted,
            ];
        };

        // 1. Array of photos (Nota, Kwitansi, Struk, Tiket)
        if (is_array($this->photos)) {
            foreach ($this->photos as $idx => $p) {
                $addFile($p, 'Struk / Nota / Kwitansi', 'Bukti Lampiran #' . ($idx + 1));
            }
        } elseif (is_string($this->photos) && !empty($this->photos)) {
            $decoded = json_decode($this->photos, true);
            if (is_array($decoded)) {
                foreach ($decoded as $idx => $p) {
                    $addFile($p, 'Struk / Nota / Kwitansi', 'Bukti Lampiran #' . ($idx + 1));
                }
            } else {
                $addFile($this->photos, 'Struk / Nota / Kwitansi', 'Bukti Lampiran');
            }
        }

        // 2. BBM photos (combined, before, after)
        $addFile($this->bbm_photo_combined, 'Foto BBM & Odometer', 'Foto Gabungan BBM (Before & After)');
        $addFile($this->bbm_photo_before, 'Foto BBM & Odometer', 'Foto Odometer BBM Sebelum');
        $addFile($this->bbm_photo_after, 'Foto BBM & Odometer', 'Foto Odometer BBM Sesudah');

        // 3. Service Motor photos
        $addFile($this->service_photo_combined, 'Foto Service Motor', 'Foto Gabungan Service (Before & After)');
        $addFile($this->service_photo_before, 'Foto Service Motor', 'Foto Sebelum Service');
        $addFile($this->service_photo_after, 'Foto Service Motor', 'Foto Sesudah Service');

        // 4. Berita Acara / WhatsApp Proof
        $addFile($this->ba_wa_proof_photo, 'Berita Acara & WhatsApp', 'Foto Bukti Persetujuan WhatsApp');

        // 5. Bukti Transfer Pencairan Finance
        $addFile($this->transfer_proof_photo, 'Pencairan Finance', 'Bukti Transfer Bank Pencairan');

        // 6. Bukti Transfer Pengembalian Sisa (User -> Finance)
        $addFile($this->return_transfer_proof, 'Pengembalian Sisa Dana', 'Bukti Transfer Pengembalian Sisa Dana');

        // 7. Line Items (Repeater)
        if (is_array($this->items)) {
            foreach ($this->items as $iIdx => $item) {
                $itemNum = $iIdx + 1;
                $itemLabel = $item['note'] ?? ($item['claim_type'] ?? "Item #{$itemNum}");

                if (!empty($item['photos'])) {
                    if (is_array($item['photos'])) {
                        foreach ($item['photos'] as $pIdx => $p) {
                            $addFile($p, 'Struk / Nota Item', "Item #{$itemNum} ({$itemLabel}) - Lampiran " . ($pIdx + 1));
                        }
                    } elseif (is_string($item['photos'])) {
                        $addFile($item['photos'], 'Struk / Nota Item', "Item #{$itemNum} ({$itemLabel}) - Lampiran");
                    }
                }
                if (!empty($item['bbm_photo_combined'])) {
                    $addFile($item['bbm_photo_combined'], 'Foto BBM & Odometer', "Item #{$itemNum} ({$itemLabel}) - Foto Gabungan");
                }
                if (!empty($item['bbm_photo_before'])) {
                    $addFile($item['bbm_photo_before'], 'Foto BBM & Odometer', "Item #{$itemNum} ({$itemLabel}) - Odometer Sebelum");
                }
                if (!empty($item['bbm_photo_after'])) {
                    $addFile($item['bbm_photo_after'], 'Foto BBM & Odometer', "Item #{$itemNum} ({$itemLabel}) - Odometer Sesudah");
                }
            }
        }

        return $files;
    }

    public function getAllAttachedFilesCount(): int
    {
        return count($this->getAllAttachedFiles());
    }
}
