<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePart extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_doc_id',
        'name',
        'position',
        'signature_info',
    ];

    public function attendanceDoc(): BelongsTo
    {
        return $this->belongsTo(AttendanceDoc::class, 'attendance_doc_id');
    }
}
