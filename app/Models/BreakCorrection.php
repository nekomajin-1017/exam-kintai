<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BreakCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_correction_id',
        'break_start_at',
        'break_end_at',
    ];

    protected $casts = [
        'break_start_at' => 'datetime',
        'break_end_at' => 'datetime',
    ];

    public function attendanceCorrection()
    {
        return $this->belongsTo(AttendanceCorrection::class);
    }
}
