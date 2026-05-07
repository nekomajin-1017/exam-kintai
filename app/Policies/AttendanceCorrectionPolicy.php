<?php

namespace App\Policies;

use App\Models\AttendanceCorrection;
use App\Models\User;

class AttendanceCorrectionPolicy
{
    // 管理者または申請者本人だけ申請詳細の閲覧を許可。
    public function view(User $user, AttendanceCorrection $correction)
    {

        return $user->is_admin || $correction->request_user_id === $user->id;
    }

    // 申請の承認操作は管理者だけ許可。
    public function approve(User $user, AttendanceCorrection $correction)
    {

        return (bool) $user->is_admin;
    }
}
