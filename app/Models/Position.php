<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'entertain_budget',
        'transport_budget',
        'bbm_budget',
        'perdin_budget',
        'service_motor_budget',
        'operational_budget',
        'signature_image',
        'description',
    ];

    protected $casts = [
        'entertain_budget'     => 'decimal:2',
        'transport_budget'     => 'decimal:2',
        'bbm_budget'           => 'decimal:2',
        'perdin_budget'        => 'decimal:2',
        'service_motor_budget' => 'decimal:2',
        'operational_budget'   => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Position $position) {
            if ($position->signature_image && Storage::disk('public')->exists($position->signature_image)) {
                Storage::disk('public')->delete($position->signature_image);
            }
        });
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function getSignatureImageUrlAttribute(): ?string
    {
        if ($this->signature_image) {
            return Storage::disk('public')->url($this->signature_image);
        }
        return null;
    }

    /**
     * Total budget allocated for this position (entertain + bbm + perdin + service motor)
     */
    public function getTotalBudgetAttribute(): float
    {
        $splitTotal = (float)$this->entertain_budget + (float)$this->bbm_budget + (float)$this->perdin_budget + (float)$this->service_motor_budget;
        if ($splitTotal > 0) {
            return $splitTotal;
        }
        return (float)$this->entertain_budget + (float)$this->operational_budget;
    }
}
