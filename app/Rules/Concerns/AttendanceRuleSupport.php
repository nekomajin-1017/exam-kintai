<?php

namespace App\Rules\Concerns;

use Carbon\Carbon;

trait AttendanceRuleSupport
{
    protected const KEY_START_TIME = 'start_time';
    protected const KEY_END_TIME = 'end_time';
    protected const MSG_INVALID_WORK_TIME = '出勤時間もしくは退勤時間が不適切な値です';
    protected const MSG_INVALID_BREAK_TIME = '休憩時間が不適切な値です';

    protected function parseTime($value)
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i', $value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
