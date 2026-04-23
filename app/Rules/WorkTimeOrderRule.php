<?php

namespace App\Rules;

use App\Rules\Concerns\HasRuleData;
use App\Rules\Concerns\AttendanceRuleSupport;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class WorkTimeOrderRule implements ValidationRule, DataAwareRule
{
    use HasRuleData;
    use AttendanceRuleSupport;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 出勤/退勤の前後関係を検証する。
        $start = $this->parseTime($value);
        $end = $this->parseTime($this->data[self::KEY_END_TIME] ?? null);

        // どちらか未入力なら順序判定しない。
        if (! $start || ! $end) {
            return;
        }

        // 出勤 > 退勤 は不正。
        if ($start->gt($end)) {
            $fail(self::MSG_INVALID_WORK_TIME);
        }
    }
}
