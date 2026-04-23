<?php

namespace App\Rules;

use App\Rules\Concerns\HasRuleData;
use App\Rules\Concerns\AttendanceRuleSupport;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class BreakStartWithinShiftRule implements ValidationRule, DataAwareRule
{
    use HasRuleData;
    use AttendanceRuleSupport;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 休憩開始時刻をパースする。
        $breakStart = $this->parseTime($value);
        // 休憩開始未入力時は本ルールをスキップする。
        if (! $breakStart) {
            return;
        }

        // 勤務時刻と比較して勤務内かを判定する。
        $start = $this->parseTime($this->data[self::KEY_START_TIME] ?? null);
        $end = $this->parseTime($this->data[self::KEY_END_TIME] ?? null);

        if ($start && $breakStart->lt($start)) {
            $fail(self::MSG_INVALID_BREAK_TIME);
            return;
        }

        if ($end && $breakStart->gt($end)) {
            $fail(self::MSG_INVALID_BREAK_TIME);
        }
    }
}
