<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    // 管理者または本人の勤怠だけ閲覧を許可。
    public function view(User $user, Attendance $attendance)
    {

        return $user->is_admin || $attendance->user_id === $user->id;
    }

    // 本人の勤怠修正申請だけ許可。
    public function store(User $user, Attendance $attendance)
    {

        return $attendance->user_id === $user->id;
    }

    // 勤怠の更新は管理者だけ許可。
    public function update(User $user, Attendance $attendance)
    {

        return (bool) $user->is_admin;
    }
}
