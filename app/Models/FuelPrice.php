<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuelPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'fuel_type',
        'price_per_liter',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'price_per_liter' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get active price for specific fuel type with fallback
     */
    public static function getPrice(string $fuelType, float $fallback = 0): float
    {
        $record = static::where('fuel_type', $fuelType)->where('is_active', true)->first();
        if ($record && (float)$record->price_per_liter > 0) {
            return (float)$record->price_per_liter;
        }

        return $fallback;
    }

    /**
     * Get active price for Pertalite (fallback: 10.000)
     */
    public static function getPertalitePrice(): float
    {
        return static::getPrice('Pertalite', 10000);
    }

    /**
     * Get active price for Pertamax (fallback: 12.300)
     */
    public static function getPertamaxPrice(): float
    {
        return static::getPrice('Pertamax', 12300);
    }

    /**
     * Get active price for Solar / Dexlite (fallback: 6.800)
     */
    public static function getSolarPrice(): float
    {
        return static::getPrice('Solar', 6800);
    }
}
