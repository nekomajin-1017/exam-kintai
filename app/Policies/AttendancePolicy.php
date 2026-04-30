<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function view(User $user, Attendance $attendance)
    {

        return $user->is_admin || $attendance->user_id === $user->id;
    }

    public function store(User $user, Attendance $attendance)
    {

        return $attendance->user_id === $user->id;
    }

    public function update(User $user, Attendance $attendance)
    {

        return (bool) $user->is_admin;
    }
}
