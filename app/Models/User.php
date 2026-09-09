<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'custom_id',
        'role_id',
        'employee_id',
        'homebase',
        'region',
        'managed_regions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'managed_regions' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            // If managed_regions is populated (array of strings), ensure region string is also kept in sync
            if (!empty($user->managed_regions) && is_array($user->managed_regions)) {
                $cleaned = array_values(array_filter(array_map('trim', array_map('strtoupper', $user->managed_regions))));
                $user->managed_regions = $cleaned;
                $user->region = implode(', ', $cleaned);
            } elseif (!empty($user->region) && empty($user->managed_regions)) {
                if (str_contains($user->region, ',')) {
                    $user->managed_regions = array_values(array_filter(array_map('trim', array_map('strtoupper', explode(',', $user->region)))));
                } else {
                    $user->managed_regions = [strtoupper(trim($user->region))];
                }
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getRoleNameAttribute(): string
    {
        return $this->role?->name ?? $this->effective_employee?->role?->name ?? 'User';
    }

    public function getRoleCodeAttribute(): string
    {
        return strtoupper($this->role?->code ?? $this->effective_employee?->role?->code ?? 'USER');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role_code === 'SUPERADMIN' || $this->email === 'admin@salesklaim.com';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role_code, ['ADMIN', 'SUPERADMIN']) || str_contains(strtoupper($this->name), 'ADMIN') || $this->email === 'admin@salesklaim.com';
    }

    public function isRgm(): bool
    {
        if ($this->role_code === 'RGM' || str_contains(strtoupper($this->role_name), 'RGM') || str_contains(strtoupper($this->name), 'RGM')) {
            return true;
        }
        return $this->effective_employee?->isRgm() ?? false;
    }

    public function isAsm(): bool
    {
        if ($this->role_code === 'ASM' || str_contains(strtoupper($this->role_name), 'ASM') || str_contains(strtoupper($this->name), 'ASM')) {
            return true;
        }
        return $this->effective_employee?->isAsm() ?? false;
    }

    public function isJejen(): bool
    {
        return $this->role_code === 'JEJEN' || str_contains(strtoupper($this->name), 'JEJEN');
    }

    public function isFinance(): bool
    {
        return $this->role_code === 'FINANCE' || str_contains(strtoupper($this->name), 'FINANCE');
    }

    public function isSales(): bool
    {
        if ($this->isAdmin() || $this->isSuperAdmin() || $this->isFinance() || $this->isJejen() || $this->isAsm() || $this->isRgm()) {
            return false;
        }

        $code = strtoupper($this->role_code);
        $name = strtoupper($this->role_name);

        if (in_array($code, ['SALES', 'SLS', 'DSF', 'ASC', 'STAFF', 'TELE']) ||
            str_contains($name, 'SALES') ||
            str_contains($name, 'DSF') ||
            str_contains($name, 'ASC')) {
            return true;
        }

        if ($this->effective_employee?->isSales()) {
            return true;
        }

        // Field user fallback: if not admin, superadmin, finance, jejen, asm, or rgm, they are sales
        return true;
    }

    public function isFieldUser(): bool
    {
        return $this->isRgm() || $this->isAsm() || $this->isSales() || (!$this->isAdmin() && !$this->isFinance() && !$this->isSuperAdmin());
    }

    /**
     * Resolves the linked Employee model (direct link or fallback by email/name match)
     */
    public function getEffectiveEmployeeAttribute(): ?Employee
    {
        if ($this->employee) {
            return $this->employee;
        }

        if ($this->employee_id) {
            $emp = Employee::find($this->employee_id);
            if ($emp) return $emp;
        }

        // Fallback match by email
        if (!empty($this->email)) {
            $emp = Employee::where('email', $this->email)->first();
            if ($emp) return $emp;
        }

        // Fallback match by name
        $cleanUserName = trim(preg_replace('/\s*\(.*?\)\s*/', '', $this->name));
        $emp = Employee::where('name', 'like', "%{$cleanUserName}%")
            ->orWhereRaw('? LIKE CONCAT("%", name, "%")', [$cleanUserName])
            ->first();

        return $emp;
    }

    /**
     * Get effective employee ID
     */
    public function getEffectiveEmployeeId(): ?int
    {
        return $this->effective_employee?->id ?? $this->employee_id;
    }

    /**
     * Get array of managed regions for Admin role (uppercase and trimmed)
     */
    public function getManagedRegionsList(): array
    {
        $regions = $this->managed_regions ?? [];
        if (is_string($regions)) {
            $decoded = json_decode($regions, true);
            $regions = is_array($decoded) ? $decoded : (empty($regions) ? [] : explode(',', $regions));
        }
        if (!is_array($regions)) {
            $regions = [];
        }
        if (empty($regions)) {
            if (!empty($this->region)) {
                $regions = str_contains($this->region, ',') ? explode(',', $this->region) : [$this->region];
            } elseif (!empty($this->homebase)) {
                $regions = str_contains($this->homebase, ',') ? explode(',', $this->homebase) : [$this->homebase];
            }
        }
        return array_values(array_filter(array_map('trim', array_map('strtoupper', $regions))));
    }

    /**
     * Alias for getManagedRegionsList
     */
    public function getRegionList(): array
    {
        return $this->getManagedRegionsList();
    }

    /**
     * Get operational region for user
     */
    public function getOperationalRegion(): string
    {
        if (!empty($this->region)) {
            return strtoupper(trim($this->region));
        }
        if (!empty($this->homebase)) {
            return strtoupper(trim($this->homebase));
        }
        if ($this->employee && !empty($this->employee->region)) {
            return strtoupper(trim($this->employee->region));
        }
        if ($this->employee && !empty($this->employee->homebase)) {
            return strtoupper(trim($this->employee->homebase));
        }
        return 'CIREBON';
    }
}
