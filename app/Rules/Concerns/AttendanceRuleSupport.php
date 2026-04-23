<?php

namespace App\Rules\Concerns;

use Carbon\Carbon;

trait AttendanceRuleSupport
{
    // 勤怠入力で共通利用するキー名。
    protected const KEY_START_TIME = 'start_time';
    protected const KEY_END_TIME = 'end_time';
    // 勤務時間/休憩時間の不正入力メッセージ。
    protected const MSG_INVALID_WORK_TIME = '出勤時間もしくは退勤時間が不適切な値です';
    protected const MSG_INVALID_BREAK_TIME = '休憩時間が不適切な値です';

    protected function parseTime($value)
    {
        // 空値は未入力として扱う。
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            // "H:i" 形式のみ受け付ける。
            return Carbon::createFromFormat('H:i', $value);
        } catch (\Throwable $e) {
            // 不正フォーマットは null を返し、呼び出し元で判定する。
            return null;
        }
    }
}
