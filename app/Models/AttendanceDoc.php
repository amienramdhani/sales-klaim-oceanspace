<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceDoc extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'employee_id',
        'claim_id',
        'claim_period_id',
        'purpose',
        'date',
        'place',
        'category',
        'file_path',
        'photos',
        'receipt_photos',
        'estimated_amount',
        'admin_status',
        'admin_notes',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'photos' => 'array',
        'receipt_photos' => 'array',
        'estimated_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (AttendanceDoc $doc) {
            if (!$doc->user_id && auth()->check()) {
                $doc->user_id = auth()->id();
            }
            if (!$doc->employee_id && auth()->check()) {
                $doc->employee_id = auth()->user()->getEffectiveEmployeeId();
            }
            if (!$doc->category) {
                $doc->category = 'Makan';
            }
            if (!$doc->admin_status) {
                $doc->admin_status = 'MENUNGGU_PENYESUAIAN';
            }
        });

        static::deleting(function (AttendanceDoc $doc) {
            if (is_array($doc->photos)) {
                foreach ($doc->photos as $photo) {
                    if ($photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($photo)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($photo);
                    }
                }
            }
            if (is_array($doc->receipt_photos)) {
                foreach ($doc->receipt_photos as $photo) {
                    if ($photo && \Illuminate\Support\Facades\Storage::disk('public')->exists($photo)) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($photo);
                    }
                }
            }
        });
    }

    public function isPendingAdminAdjustment(): bool
    {
        return empty($this->claim_id) || $this->admin_status === 'MENUNGGU_PENYESUAIAN';
    }

    public function isAdjusted(): bool
    {
        return !empty($this->claim_id) || $this->admin_status === 'SUDAH_DISESUAIKAN';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function claimPeriod(): BelongsTo
    {
        return $this->belongsTo(ClaimPeriod::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(AttendancePart::class, 'attendance_doc_id');
    }

    public function getEffectiveEmployeeAttribute(): ?Employee
    {
        return $this->employee ?? $this->claim?->employee ?? $this->claimPeriod?->employee;
    }
}
