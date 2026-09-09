<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'brand',
        'reffnote',
        'city',
        'status',
    ];

    public function claims(): HasMany
    {
        return $this->hasMany(Claim::class);
    }
}
