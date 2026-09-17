<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'custom_id',
        'position_id',
        'role_id',
        'supervisor_id',
        'name',
        'position',
        'phone',
        'email',
        'homebase',
        'region',
        'bank_name',
        'bank_account_number',
        'bank_account_name',
        'bbm_budget',
        'entertain_budget',
        'perdin_budget',
        'perdin_meal_allowance',
        'perdin_lodging_allowance',
        'perdin_transport_budget',
        'transport_budget',
        'signature_image',
        'status',
    ];

    protected $casts = [
        'bbm_budget' => 'decimal:2',
        'entertain_budget' => 'decimal:2',
        'perdin_budget' => 'decimal:2',
        'perdin_meal_allowance' => 'decimal:2',
        'perdin_lodging_allowance' => 'decimal:2',
        'perdin_transport_budget' => 'decimal:2',
        'transport_budget' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (Employee $employee) {
            if (is_null($employee->bbm_budget)) $employee->bbm_budget = 0;
            if (is_null($employee->perdin_budget)) $employee->perdin_budget = 0;
            if (is_null($employee->transport_budget)) $employee->transport_budget = 0;
            if (is_null($employee->perdin_meal_allowance)) $employee->perdin_meal_allowance = 100000;
            if (is_null($employee->perdin_lodging_allowance)) $employee->perdin_lodging_allowance = 250000;
            if (is_null($employee->perdin_transport_budget)) $employee->perdin_transport_budget = 500000;
        });

        static::deleting(function (Employee $employee) {
            if ($employee->signature_image && Storage::disk('public')->exists($employee->signature_image)) {
                Storage::disk('public')->delete($employee->signature_image);
            }
        });
    }

    public function isAsm(): bool
    {
        $roleName = strtoupper($this->role?->name ?? '');
        $roleCode = strtoupper($this->role?->code ?? '');
        $posName = strtoupper($this->position_name ?? ($this->position ?? ''));
        return str_contains($roleName, 'ASM') || str_contains($roleCode, 'ASM') || str_contains($posName, 'ASM') || str_contains($posName, 'AREA SALES');
    }

    public function isRgm(): bool
    {
        $roleName = strtoupper($this->role?->name ?? '');
        $roleCode = strtoupper($this->role?->code ?? '');
        $posName = strtoupper($this->position_name ?? ($this->position ?? ''));
        return str_contains($roleName, 'RGM') || str_contains($roleCode, 'RGM') || str_contains($posName, 'RGM') || str_contains($posName, 'REGIONAL GENERAL');
    }

    public function isSales(): bool
    {
        if ($this->isAsm() || $this->isRgm()) {
            return false;
        }

        $roleName = strtoupper($this->role?->name ?? '');
        $roleCode = strtoupper($this->role?->code ?? '');
        $posName = strtoupper($this->position_name ?? ($this->position ?? ''));
        return in_array($roleName, ['SALES', 'DSF', 'ASC', 'STAFF']) ||
               in_array($roleCode, ['SALES', 'SLS', 'DSF', 'ASC', 'STAFF', 'TELE']) ||
               str_contains($posName, 'SALES') ||
               str_contains($posName, 'DSF') ||
               str_contains($roleName, 'SALES');
    }

    public function positionModel(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    public function claimPeriods(): HasMany
    {
        return $this->hasMany(ClaimPeriod::class);
    }

    /**
     * Atasan langsung (RGM -> supervisor ASM, ASM -> supervisor Sales)
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    /**
     * Bawahan langsung
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'supervisor_id');
    }

    /**
     * Get position / role display name
     */
    public function getPositionNameAttribute(): string
    {
        return $this->role?->name ?? $this->positionModel?->name ?? $this->position ?? 'Staff';
    }

    /**
     * Get total budget allocated for this employee (sum of individual categories)
     */
    public function getTotalBudgetAttribute(): float
    {
        return (float)$this->bbm_budget + (float)$this->entertain_budget + (float)$this->perdin_budget + (float)$this->transport_budget;
    }

    /**
     * Get entertain budget (Priority: Custom per Employee -> Position standard -> Role standard -> Default by role)
     */
    public function getEntertainBudgetAttribute(): float
    {
        if (isset($this->attributes['entertain_budget']) && (float)$this->attributes['entertain_budget'] > 0) {
            return (float)$this->attributes['entertain_budget'];
        }
        if ($this->positionModel && (float)$this->positionModel->entertain_budget > 0) {
            return (float)$this->positionModel->entertain_budget;
        }
        if ($this->role && (float)$this->role->entertain_budget > 0) {
            return (float)$this->role->entertain_budget;
        }
        $roleCode = strtoupper($this->role?->code ?? '');
        return match (true) {
            str_contains($roleCode, 'RGM') => 2400000.0,
            str_contains($roleCode, 'ASM') => 1500000.0,
            default => 0.0,
        };
    }

    /**
     * Get BBM budget (diatur fleksibel per karyawan oleh Super Admin)
     */
    public function getBbmBudgetAttribute(): float
    {
        if (isset($this->attributes['bbm_budget']) && (float)$this->attributes['bbm_budget'] > 0) {
            return (float)$this->attributes['bbm_budget'];
        }
        if ($this->positionModel && (float)$this->positionModel->bbm_budget > 0) {
            return (float)$this->positionModel->bbm_budget;
        }
        if ($this->role && (float)$this->role->fuel_budget_default > 0) {
            return (float)$this->role->fuel_budget_default;
        }
        return 0;
    }

    /**
     * Get Perjalanan Dinas budget (diatur per karyawan)
     */
    public function getPerdinBudgetAttribute(): float
    {
        if (isset($this->attributes['perdin_budget']) && (float)$this->attributes['perdin_budget'] > 0) {
            return (float)$this->attributes['perdin_budget'];
        }
        if ($this->positionModel && (float)$this->positionModel->perdin_budget > 0) {
            return (float)$this->positionModel->perdin_budget;
        }
        return 0;
    }

    /**
     * Get Service Motor budget
     */
    public function getServiceMotorBudgetAttribute(): float
    {
        if ($this->positionModel && (float)$this->positionModel->service_motor_budget > 0) {
            return (float)$this->positionModel->service_motor_budget;
        }
        if ($this->role && (float)$this->role->service_motor_budget_quarterly > 0) {
            return (float)$this->role->service_motor_budget_quarterly;
        }
        return 0;
    }

    /**
     * Get Transport/Tol/Parkir budget (diatur per karyawan, fallback ke standar)
     */
    public function getTransportBudgetAttribute(): float
    {
        if (isset($this->attributes['transport_budget']) && (float)$this->attributes['transport_budget'] > 0) {
            return (float)$this->attributes['transport_budget'];
        }
        if ($this->positionModel && (float)$this->positionModel->transport_budget > 0) {
            return (float)$this->positionModel->transport_budget;
        }
        if ($this->role && isset($this->role->transport_budget) && (float)$this->role->transport_budget > 0) {
            return (float)$this->role->transport_budget;
        }
        $roleCode = strtoupper($this->role?->code ?? '');
        return match (true) {
            str_contains($roleCode, 'RGM') => 2400000.0,
            str_contains($roleCode, 'ASM') => 1500000.0,
            default => 0.0,
        };
    }

    protected array $periodUsageCache = [];

    /**
     * Get used BBM claims for specific period (or all time)
     */
    public function getUsedBbmForPeriod(?int $month = null, ?int $year = null): float
    {
        $cacheKey = "bbm_" . ($month ?? 'all') . "_" . ($year ?? 'all');
        if (isset($this->periodUsageCache[$cacheKey])) {
            return $this->periodUsageCache[$cacheKey];
        }

        // If claims are already loaded in memory, filter collection to avoid DB queries
        if ($this->relationLoaded('claims')) {
            $claims = $this->claims->filter(function ($c) use ($month, $year) {
                if ($month && (int)$c->claim_date?->month !== (int)$month) return false;
                if ($year && (int)$c->claim_date?->year !== (int)$year) return false;
                $cat = $c->claim_category ?? '';
                $type = is_array($c->claim_type) ? implode(' ', $c->claim_type) : ($c->claim_type ?? '');
                return $cat === 'bbm' || str_contains(strtoupper($type), 'BBM') || !empty($c->bbm_photo_combined) || !empty($c->bbm_photo_before);
            });
        } else {
            $query = $this->claims();
            if ($month) $query->whereMonth('claim_date', $month);
            if ($year) $query->whereYear('claim_date', $year);

            $claims = $query->where(function ($q) {
                $q->where('claim_category', 'bbm')
                  ->orWhere('claim_type', 'like', '%BBM%')
                  ->orWhereNotNull('bbm_photo_combined')
                  ->orWhereNotNull('bbm_photo_before');
            })->get();
        }

        $used = 0;
        foreach ($claims as $claim) {
            if (is_array($claim->items) && count($claim->items) > 0) {
                foreach ($claim->items as $it) {
                    $type = is_array($it['claim_type'] ?? null) ? implode(', ', $it['claim_type']) : ($it['claim_type'] ?? 'BBM');
                    if (str_contains(strtoupper($type), 'BBM')) {
                        $used += (float)($it['amount'] ?? 0);
                    }
                }
            } else {
                $used += (float)$claim->amount;
            }
        }
        return $this->periodUsageCache[$cacheKey] = $used;
    }

    /**
     * Get BBM claims for specific period
     */
    public function getBbmClaims(?int $month = null, ?int $year = null)
    {
        $query = $this->claims()->orderBy('claim_date', 'desc');
        if ($month) $query->whereMonth('claim_date', $month);
        if ($year) $query->whereYear('claim_date', $year);

        return $query->where(function ($q) {
            $q->where('claim_category', 'bbm')
              ->orWhere('claim_type', 'like', '%BBM%')
              ->orWhereNotNull('bbm_photo_combined')
              ->orWhereNotNull('bbm_photo_before');
        })->get();
    }

    /**
     * Get BBM claims count for specific period
     */
    public function getBbmClaimsCountForPeriod(?int $month = null, ?int $year = null): int
    {
        if ($this->relationLoaded('claims')) {
            return $this->claims->filter(function ($c) use ($month, $year) {
                if ($month && (int)$c->claim_date?->month !== (int)$month) return false;
                if ($year && (int)$c->claim_date?->year !== (int)$year) return false;
                $cat = $c->claim_category ?? '';
                $type = is_array($c->claim_type) ? implode(' ', $c->claim_type) : ($c->claim_type ?? '');
                return $cat === 'bbm' || str_contains(strtoupper($type), 'BBM') || !empty($c->bbm_photo_combined) || !empty($c->bbm_photo_before);
            })->count();
        }

        $query = $this->claims();
        if ($month) $query->whereMonth('claim_date', $month);
        if ($year) $query->whereYear('claim_date', $year);

        return $query->where(function ($q) {
            $q->where('claim_category', 'bbm')
              ->orWhere('claim_type', 'like', '%BBM%')
              ->orWhereNotNull('bbm_photo_combined')
              ->orWhereNotNull('bbm_photo_before');
        })->count();
    }

    /**
     * Get used Entertain claims for specific period
     */
    public function getUsedEntertainForPeriod(?int $month = null, ?int $year = null, bool $onlyMakan = true): float
    {
        $cacheKey = "ent_" . ($month ?? 'all') . "_" . ($year ?? 'all') . "_" . ($onlyMakan ? 'makan' : 'all');
        if (isset($this->periodUsageCache[$cacheKey])) {
            return $this->periodUsageCache[$cacheKey];
        }

        if ($this->relationLoaded('claims')) {
            $claims = $this->claims->filter(function ($c) use ($month, $year) {
                if ($month && (int)$c->claim_date?->month !== (int)$month) return false;
                if ($year && (int)$c->claim_date?->year !== (int)$year) return false;
                $cat = $c->claim_category ?? '';
                $type = is_array($c->claim_type) ? implode(' ', $c->claim_type) : ($c->claim_type ?? '');
                $itemsStr = is_array($c->items) ? json_encode($c->items) : ($c->items ?? '');
                return $cat === 'transport_entertain' || str_contains(strtoupper($type), 'ENTERTAIN') || str_contains(strtoupper($itemsStr), 'ENTERTAIN');
            });
        } else {
            $query = $this->claims();
            if ($month) $query->whereMonth('claim_date', $month);
            if ($year) $query->whereYear('claim_date', $year);

            $claims = $query->where(function ($q) {
                $q->where('claim_category', 'transport_entertain')
                  ->orWhere('claim_type', 'like', '%Entertain%')
                  ->orWhere('items', 'like', '%Entertain%');
            })->get();
        }

        $used = 0;
        foreach ($claims as $claim) {
            foreach ($claim->getLineItems() as $item) {
                $type = is_array($item['claim_type'] ?? null) ? implode(', ', $item['claim_type']) : ($item['claim_type'] ?? '');
                $subtype = $item['entertain_subtype'] ?? ($claim->entertain_subtype ?? 'Makan');
                if (str_contains(strtoupper($type), 'ENTERTAIN') || str_contains(strtoupper($type), 'MAKAN')) {
                    if (!$onlyMakan || strtoupper($subtype) !== 'LAINNYA') {
                        $used += (float)($item['amount'] ?? 0);
                    }
                }
            }
        }
        return $this->periodUsageCache[$cacheKey] = $used;
    }

    /**
     * Get Entertain claims for specific period
     */
    public function getEntertainClaims(?int $month = null, ?int $year = null)
    {
        $query = $this->claims()->orderBy('claim_date', 'desc');
        if ($month) $query->whereMonth('claim_date', $month);
        if ($year) $query->whereYear('claim_date', $year);

        return $query->where(function ($q) {
            $q->where('claim_category', 'transport_entertain')
              ->orWhere('claim_type', 'like', '%Entertain%')
              ->orWhere('items', 'like', '%Entertain%');
        })->get();
    }

    /**
     * Get Entertain claims count for specific period
     */
    public function getEntertainClaimsCountForPeriod(?int $month = null, ?int $year = null): int
    {
        if ($this->relationLoaded('claims')) {
            return $this->claims->filter(function ($c) use ($month, $year) {
                if ($month && (int)$c->claim_date?->month !== (int)$month) return false;
                if ($year && (int)$c->claim_date?->year !== (int)$year) return false;
                $cat = $c->claim_category ?? '';
                $type = is_array($c->claim_type) ? implode(' ', $c->claim_type) : ($c->claim_type ?? '');
                $itemsStr = is_array($c->items) ? json_encode($c->items) : ($c->items ?? '');
                return $cat === 'transport_entertain' || str_contains(strtoupper($type), 'ENTERTAIN') || str_contains(strtoupper($itemsStr), 'ENTERTAIN');
            })->count();
        }

        $query = $this->claims();
        if ($month) $query->whereMonth('claim_date', $month);
        if ($year) $query->whereYear('claim_date', $year);

        return $query->where(function ($q) {
            $q->where('claim_category', 'transport_entertain')
              ->orWhere('claim_type', 'like', '%Entertain%')
              ->orWhere('items', 'like', '%Entertain%');
        })->count();
    }

    /**
     * Get used Perjalanan Dinas claims for specific period
     */
    public function getUsedPerdinForPeriod(?int $month = null, ?int $year = null): float
    {
        $cacheKey = "perdin_" . ($month ?? 'all') . "_" . ($year ?? 'all');
        if (isset($this->periodUsageCache[$cacheKey])) {
            return $this->periodUsageCache[$cacheKey];
        }

        if ($this->relationLoaded('claims')) {
            $claims = $this->claims->filter(function ($c) use ($month, $year) {
                if ($month && (int)$c->claim_date?->month !== (int)$month) return false;
                if ($year && (int)$c->claim_date?->year !== (int)$year) return false;
                $cat = $c->claim_category ?? '';
                $type = is_array($c->claim_type) ? implode(' ', $c->claim_type) : ($c->claim_type ?? '');
                $itemsStr = is_array($c->items) ? json_encode($c->items) : ($c->items ?? '');
                return !empty($c->is_perdin) || $cat === 'perdin' || str_contains(strtoupper($type), 'PERJALANAN DINAS') || str_contains(strtoupper($itemsStr), 'PERJALANAN DINAS');
            });
        } else {
            $query = $this->claims();
            if ($month) $query->whereMonth('claim_date', $month);
            if ($year) $query->whereYear('claim_date', $year);

            $claims = $query->where(function ($q) {
                $q->where('is_perdin', true)
                  ->orWhere('claim_category', 'perdin')
                  ->orWhere('claim_type', 'like', '%Perjalanan Dinas%')
                  ->orWhere('items', 'like', '%Perjalanan Dinas%');
            })->get();
        }

        $used = 0;
        foreach ($claims as $claim) {
            if ($claim->is_perdin) {
                $used += (float)$claim->amount;
            } else {
                foreach ($claim->getLineItems() as $item) {
                    $type = is_array($item['claim_type'] ?? null) ? implode(', ', $item['claim_type']) : ($item['claim_type'] ?? '');
                    if (str_contains(strtoupper($type), 'PERJALANAN DINAS') || str_contains(strtoupper($type), 'PERDIN')) {
                        $used += (float)($item['amount'] ?? 0);
                    }
                }
            }
        }
        return $this->periodUsageCache[$cacheKey] = $used;
    }

    /**
     * Get used Transport / Service claims for specific period
     */
    public function getUsedTransportForPeriod(?int $month = null, ?int $year = null): float
    {
        $cacheKey = "trans_" . ($month ?? 'all') . "_" . ($year ?? 'all');
        if (isset($this->periodUsageCache[$cacheKey])) {
            return $this->periodUsageCache[$cacheKey];
        }

        if ($this->relationLoaded('claims')) {
            $claims = $this->claims->filter(function ($c) use ($month, $year) {
                if ($month && (int)$c->claim_date?->month !== (int)$month) return false;
                if ($year && (int)$c->claim_date?->year !== (int)$year) return false;
                $cat = $c->claim_category ?? '';
                $type = is_array($c->claim_type) ? implode(' ', $c->claim_type) : ($c->claim_type ?? '');
                $itemsStr = is_array($c->items) ? json_encode($c->items) : ($c->items ?? '');
                $upper = strtoupper($type . ' ' . $itemsStr);
                return $cat === 'transport_entertain' || str_contains($upper, 'TRANSPORT') || str_contains($upper, 'TOL') || str_contains($upper, 'SERVICE') || str_contains($upper, 'PARKIR');
            });
        } else {
            $query = $this->claims();
            if ($month) $query->whereMonth('claim_date', $month);
            if ($year) $query->whereYear('claim_date', $year);

            $claims = $query->where(function ($q) {
                $q->where('claim_category', 'transport_entertain')
                  ->orWhere('claim_type', 'like', '%Transport%')
                  ->orWhere('claim_type', 'like', '%Tol%')
                  ->orWhere('claim_type', 'like', '%Service%')
                  ->orWhere('claim_type', 'like', '%Parkir%')
                  ->orWhere('items', 'like', '%Transport%')
                  ->orWhere('items', 'like', '%Tol%')
                  ->orWhere('items', 'like', '%Service%')
                  ->orWhere('items', 'like', '%Parkir%');
            })->get();
        }

        $used = 0;
        foreach ($claims as $claim) {
            foreach ($claim->getLineItems() as $item) {
                $type = is_array($item['claim_type'] ?? null) ? implode(', ', $item['claim_type']) : ($item['claim_type'] ?? '');
                $upper = strtoupper($type);
                if (str_contains($upper, 'TRANSPORT') || str_contains($upper, 'TOL') || str_contains($upper, 'PARKIR') || str_contains($upper, 'SERVICE') || str_contains($upper, 'SEWA MOBIL')) {
                    $used += (float)($item['amount'] ?? 0);
                }
            }
        }
        return $this->periodUsageCache[$cacheKey] = $used;
    }

    /**
     * Get total used expense claims for specific period
     */
    public function getTotalExpenseUsedForPeriod(?int $month = null, ?int $year = null): float
    {
        $cacheKey = "total_" . ($month ?? 'all') . "_" . ($year ?? 'all');
        if (isset($this->periodUsageCache[$cacheKey])) {
            return $this->periodUsageCache[$cacheKey];
        }

        if ($this->relationLoaded('claims')) {
            $used = (float)$this->claims->filter(function ($c) use ($month, $year) {
                if ($month && (int)$c->claim_date?->month !== (int)$month) return false;
                if ($year && (int)$c->claim_date?->year !== (int)$year) return false;
                return true;
            })->sum('amount');
        } else {
            $query = $this->claims();
            if ($month) $query->whereMonth('claim_date', $month);
            if ($year) $query->whereYear('claim_date', $year);
            $used = (float) $query->sum('amount');
        }

        return $this->periodUsageCache[$cacheKey] = $used;
    }

    /**
     * Get total used expense claims
     */
    public function getTotalExpenseUsedAttribute(): float
    {
        return $this->getTotalExpenseUsedForPeriod();
    }

    /**
     * Get used entertain claims (specifically Makan sub-type)
     */
    public function getUsedEntertainMakanAttribute(): float
    {
        return $this->getUsedEntertainForPeriod(null, null, true);
    }

    /**
     * Get used BBM claims
     */
    public function getUsedBbmAttribute(): float
    {
        return $this->getUsedBbmForPeriod(null, null);
    }

    /**
     * Get used Perdin claims
     */
    public function getUsedPerdinAttribute(): float
    {
        return $this->getUsedPerdinForPeriod(null, null);
    }

    /**
     * Get remaining balance
     */
    public function getRemainingBudgetAttribute(): float
    {
        $budget = $this->total_budget;
        $used = $this->total_expense_used;
        return $budget - $used;
    }

    /**
     * Get signature image url
     */
    public function getSignatureUrlAttribute(): ?string
    {
        return $this->signature_image ? Storage::disk('public')->url($this->signature_image) : null;
    }
}
