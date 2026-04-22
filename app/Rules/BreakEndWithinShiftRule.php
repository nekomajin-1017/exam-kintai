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
        $breakEnd = $this->parseTime($value);
        $start = $this->parseTime($this->data[self::KEY_START_TIME] ?? null);
        $end = $this->parseTime($this->data[self::KEY_END_TIME] ?? null);

        if (! $breakEnd) {
            return;
        }

        if ($start && $breakEnd->lt($start)) {
            $fail(self::MSG_INVALID_BREAK_TIME);
            return;
        }

        if ($end && $breakEnd->gt($end)) {
            $fail(self::MSG_INVALID_BREAK_TIME);
        }
    }
}
