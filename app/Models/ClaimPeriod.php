<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClaimPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'period_number',
        'employee_id',
        'month',
        'year',
        'submission_date',
        'budget_claim',
        'already_claimed',
        'total_claim',
        'over_budget',
        'status',
        'approval_status',
        'disbursement_status',
        'disbursed_at',
        'notes',
    ];

    protected $casts = [
        'submission_date' => 'date',
        'budget_claim' => 'decimal:2',
        'already_claimed' => 'decimal:2',
        'total_claim' => 'decimal:2',
        'over_budget' => 'decimal:2',
        'disbursed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ClaimPeriod $period) {
            if (!$period->user_id && auth()->check()) {
                $period->user_id = auth()->id();
            }
            if (!$period->employee_id && auth()->check()) {
                $period->employee_id = auth()->user()->getEffectiveEmployeeId();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }

    public function attendanceDocs(): HasMany
    {
        return $this->hasMany(AttendanceDoc::class);
    }

    /**
     * Scope a query to only include claim periods visible to the given user.
     */
    public function scopeVisibleToUser(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdmin()) {
            $managedRegions = $user->getManagedRegionsList();

            return $query->where(function (Builder $q) use ($user, $managedRegions) {
                $q->where('user_id', $user->id);

                if (!empty($managedRegions)) {
                    $q->orWhereHas('employee', function (Builder $empQuery) use ($managedRegions) {
                        $empQuery->where(function (Builder $eq) use ($managedRegions) {
                            foreach ($managedRegions as $region) {
                                $eq->orWhereRaw('UPPER(employees.region) LIKE ?', ['%' . strtoupper($region) . '%'])
                                   ->orWhereRaw('UPPER(employees.homebase) LIKE ?', ['%' . strtoupper($region) . '%']);
                            }
                        });
                    });
                    $q->orWhereHas('user', function (Builder $uQuery) use ($managedRegions) {
                        $uQuery->where(function (Builder $uq) use ($managedRegions) {
                            foreach ($managedRegions as $region) {
                                $uq->orWhereRaw('UPPER(users.region) LIKE ?', ['%' . strtoupper($region) . '%'])
                                   ->orWhereRaw('UPPER(users.homebase) LIKE ?', ['%' . strtoupper($region) . '%']);
                            }
                        });
                    });
                }
            });
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
     * Recalculate total claims and over budget values
     */
    public function recalculateTotals(): void
    {
        $total = $this->claims()->sum('amount');
        $this->total_claim = $total;

        // Over budget calculation
        $totalPlusAlready = (float) $total + (float) ($this->already_claimed ?? 0);
        $budget = (float) ($this->budget_claim ?? 0);

        if ($budget > 0 && $totalPlusAlready > $budget) {
            $this->over_budget = $totalPlusAlready - $budget;
        } else {
            $this->over_budget = 0;
        }

        $this->saveQuietly();
    }

    /**
     * Generate unique submission number if not present
     */
    public static function generatePeriodNumber(int $year, string $month): string
    {
        $count = static::where('year', $year)->count() + 1;
        $monthStr = is_numeric($month) ? str_pad($month, 2, '0', STR_PAD_LEFT) : date('m', strtotime($month));
        return sprintf("CLM/%04d/%02d/%03d", $year, (int)$monthStr, $count);
    }
}
