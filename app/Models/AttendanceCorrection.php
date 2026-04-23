<?php

namespace App\Models;

use App\Constants\ApprovalStatusCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceCorrection extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'request_user_id',
        'requested_check_in_at',
        'requested_check_out_at',
        'reason',
        'approval_status_code',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'requested_check_in_at' => 'datetime',
        'requested_check_out_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    public function requestUser()
    {
        return $this->belongsTo(User::class, 'request_user_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function breakCorrections()
    {
        return $this->hasMany(BreakCorrection::class);
    }

    public function scopePending($query)
    {
        return $query->where('approval_status_code', ApprovalStatusCode::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('approval_status_code', ApprovalStatusCode::APPROVED);
    }

    public function scopeForTab($query, $tab)
    {
        if ($tab === 'approved') {
            return $this->scopeApproved($query);
        }

        return $this->scopePending($query);
    }
}
