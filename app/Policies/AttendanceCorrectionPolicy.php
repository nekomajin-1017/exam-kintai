<?php

namespace App\Policies;

use App\Models\AttendanceCorrection;
use App\Models\User;

class AttendanceCorrectionPolicy
{
    public function view(User $user, AttendanceCorrection $correction)
    {

        return $user->is_admin || $correction->request_user_id === $user->id;
    }

    public function approve(User $user, AttendanceCorrection $correction)
    {

        return (bool) $user->is_admin;
    }
}
