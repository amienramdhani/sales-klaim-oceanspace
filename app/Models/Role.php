<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'entertain_budget',
        'operational_budget',
        'meal_allowance_per_day',
        'lodging_allowance_per_night',
        'toll_allowance',
        'fuel_budget_default',
        'car_rental_budget_default',
        'service_car_budget_quarterly',
        'service_motor_budget_quarterly',
        'status',
    ];

    protected $casts = [
        'entertain_budget' => 'decimal:2',
        'operational_budget' => 'decimal:2',
        'meal_allowance_per_day' => 'decimal:2',
        'lodging_allowance_per_night' => 'decimal:2',
        'toll_allowance' => 'decimal:2',
        'fuel_budget_default' => 'decimal:2',
        'car_rental_budget_default' => 'decimal:2',
        'service_car_budget_quarterly' => 'decimal:2',
        'service_motor_budget_quarterly' => 'decimal:2',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Total standard monthly budget for role
     */
    public function getTotalBudgetAttribute(): float
    {
        return (float)$this->entertain_budget + (float)$this->operational_budget;
    }
}
