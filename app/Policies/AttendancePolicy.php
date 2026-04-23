<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function view(User $user, Attendance $attendance)
    {
        // 管理者は全件、一般ユーザーは自分の勤怠のみ閲覧可。
        return $user->is_admin || $attendance->user_id === $user->id;
    }

    public function store(User $user, Attendance     $attendance)
    {
        // 修正申請は本人のみ作成可。
        return $attendance->user_id === $user->id;
    }

    public function update(User $user, Attendance $attendance)
    {
        // 勤怠の直接更新は管理者のみ許可。
        return (bool) $user->is_admin;
    }
}
