<?php

namespace App\Rules;

use App\Rules\Concerns\HasRuleData;
use App\Rules\Concerns\AttendanceRuleSupport;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class BreakEndWithinShiftRule implements ValidationRule, DataAwareRule
{
    use HasRuleData;
    use AttendanceRuleSupport;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 終了時刻と勤務時間を同じ形式で評価する。
        $breakEnd = $this->parseTime($value);
        $start = $this->parseTime($this->data[self::KEY_START_TIME] ?? null);
        $end = $this->parseTime($this->data[self::KEY_END_TIME] ?? null);

        // 休憩終了が未入力なら本ルールでは判定しない。
        if (! $breakEnd) {
            return;
        }

        // 勤務開始より前の休憩終了は不可。
        if ($start && $breakEnd->lt($start)) {
            $fail(self::MSG_INVALID_BREAK_TIME);
            return;
        }

        // 勤務終了より後の休憩終了は不可。
        if ($end && $breakEnd->gt($end)) {
            $fail(self::MSG_INVALID_BREAK_TIME);
        }
    }
}
