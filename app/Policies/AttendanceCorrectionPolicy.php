<?php

namespace App\Policies;

class AttendanceCorrectionPolicy
{
    public function view($user, $correction)
    {
        // 管理者は全件、一般ユーザーは自分の申請のみ閲覧可。
        return $user->is_admin || $correction->request_user_id === $user->id;
    }

    public function approve($user, $correction)
    {
        // 申請承認は管理者のみ許可。
        return (bool) $user->is_admin;
    }
}
