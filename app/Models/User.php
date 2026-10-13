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
        'managed_branches',
        'company',
        'badan_usaha',
        'telegram_chat_id',
        'telegram_username',
        'must_change_pin',
        'initial_pin',
        'pin_changed_at',
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
            'managed_branches' => 'array',
            'must_change_pin' => 'boolean',
            'pin_changed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            // Auto link employee_id if custom_id matches employee
            if (empty($user->employee_id) && !empty($user->custom_id)) {
                $matchedEmp = Employee::where('custom_id', trim($user->custom_id))->first();
                if ($matchedEmp) {
                    $user->employee_id = $matchedEmp->id;
                }
            }

            // Auto sync custom_id from employee if empty
            if (empty($user->custom_id) && $user->employee_id) {
                $emp = Employee::find($user->employee_id);
                if ($emp && !empty($emp->custom_id)) {
                    $user->custom_id = $emp->custom_id;
                }
            }

            // Auto generate initial PIN if empty on new user
            if (empty($user->password)) {
                $pin = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                $user->initial_pin = $pin;
                $user->password = \Illuminate\Support\Facades\Hash::make($pin);
                $user->must_change_pin = true;
            }

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

        static::saved(function (User $user) {
            if ($user->employee_id) {
                $emp = Employee::find($user->employee_id);
                if ($emp) {
                    $empUpdates = [];
                    if ($user->name && $emp->name !== $user->name) {
                        $empUpdates['name'] = $user->name;
                    }
                    if ($user->custom_id && $emp->custom_id !== $user->custom_id) {
                        $empUpdates['custom_id'] = $user->custom_id;
                    }
                    if ($user->role_id && $emp->role_id !== $user->role_id) {
                        $empUpdates['role_id'] = $user->role_id;
                    }
                    if (!empty($empUpdates)) {
                        $emp->updateQuietly($empUpdates);
                    }
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

    public function isYoga(): bool
    {
        return in_array($this->role_code, ['YOGA', 'MGR_FINANCE', 'MANAGER_FINANCE']) || 
               str_contains(strtoupper($this->name), 'YOGA') ||
               str_contains(strtoupper($this->role_name), 'PAK YOGA') ||
               str_contains(strtoupper($this->role_name), 'MANAGER FINANCE');
    }

    public function isNurbaiti(): bool
    {
        return in_array($this->role_code, ['NURBAITI', 'NURBAETI', 'FIN_COORD', 'KOORDINATOR_FINANCE']) || 
               str_contains(strtoupper($this->name), 'NURBAITI') ||
               str_contains(strtoupper($this->name), 'NURBAETI') ||
               str_contains(strtoupper($this->name), 'RIASEHA') ||
               str_contains(strtoupper($this->role_name), 'NURBAITI') ||
               str_contains(strtoupper($this->role_name), 'NURBAETI') ||
               str_contains(strtoupper($this->role_name), 'FINANCE COORDINATOR') ||
               str_contains(strtoupper($this->role_name), 'KOORDINATOR FINANCE');
    }

    public function isThia(): bool
    {
        return in_array($this->role_code, ['THIA', 'MGR_KEUANGAN_CS', 'MANAJER_KEUANGAN']) || 
               str_contains(strtoupper($this->name), 'THIA') ||
               str_contains(strtoupper($this->role_name), 'THIA') ||
               str_contains(strtoupper($this->role_name), 'MANAJER KEUANGAN');
    }

    public function isExecutiveApprover(): bool
    {
        return $this->isJejen() || $this->isYoga() || $this->isNurbaiti() || $this->isThia();
    }

    public function isFinance(): bool
    {
        if ($this->isExecutiveApprover()) {
            return false;
        }

        return in_array($this->role_code, ['FINANCE', 'FINANCE_MSI', 'FINANCE_CVCS', 'FINANCE_TOP']) || 
               str_contains(strtoupper($this->name), 'FINANCE') ||
               str_contains(strtoupper($this->role_name), 'FINANCE');
    }

    public function isFinanceMsi(): bool
    {
        return $this->role_code === 'FINANCE_MSI' || 
               ($this->isFinance() && ($this->badan_usaha === 'PT MEDIA SELULAR INDONESIA' || str_contains(strtoupper($this->badan_usaha ?? ''), 'MEDIA SELULAR') || str_contains(strtoupper($this->badan_usaha ?? ''), 'MSI') || str_contains(strtoupper($this->name), 'MSI')));
    }

    public function isFinanceTop(): bool
    {
        return in_array($this->role_code, ['FINANCE_TOP', 'FINANCE_CVCS']) || 
               ($this->isFinance() && (str_contains(strtoupper($this->badan_usaha ?? ''), 'TOP') || str_contains(strtoupper($this->badan_usaha ?? ''), 'COMPLETE') || str_contains(strtoupper($this->name), 'TOP') || str_contains(strtoupper($this->name), 'COMPLETE') || str_contains(strtoupper($this->name), 'CS')));
    }

    public function isFinanceCvcs(): bool
    {
        return $this->isFinanceTop();
    }

    public function getManagedBranchesList(): array
    {
        if (is_array($this->managed_branches)) {
            return $this->managed_branches;
        }
        if (!empty($this->managed_branches)) {
            $decoded = json_decode($this->managed_branches, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    public function isSales(): bool
    {
        if ($this->isAdmin() || $this->isSuperAdmin() || $this->isFinance() || $this->isJejen() || $this->isYoga() || $this->isNurbaiti() || $this->isThia() || $this->isAsm() || $this->isRgm()) {
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

        // Field user fallback: if not management or admin, they are sales
        return true;
    }

    public function isFieldUser(): bool
    {
        return $this->isRgm() || $this->isAsm() || $this->isSales();
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
