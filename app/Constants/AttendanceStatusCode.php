<?php

namespace App\Constants;

final class AttendanceStatusCode
{
    // 未出勤。
    public const OFF = 'off';
    // 勤務中。
    public const WORKING = 'working';
    // 休憩中。
    public const ON_BREAK = 'on_break';
    // 退勤済み。
    public const FINISHED = 'finished';
}
