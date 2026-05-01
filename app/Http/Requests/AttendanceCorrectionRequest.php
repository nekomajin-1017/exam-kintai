<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:1000'],
            'break_start_at' => ['nullable', 'array'],
            'break_start_at.*' => ['nullable', 'date_format:H:i'],
            'break_end_at' => ['nullable', 'array'],
            'break_end_at.*' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => '備考を記入してください',
            'start_time.date_format' => '入力した時間が不適切です',
            'end_time.date_format' => '入力した時間が不適切です',
            'break_start_at.*.date_format' => '入力した時間が不適切です',
            'break_end_at.*.date_format' => '入力した時間が不適切です',
        ];
    }

    protected function prepareForValidation(): void
    {
        $attendance = $this->route('attendance');

        if (! $attendance) {
            return;
        }

        $fallback = [];

        if (blank($this->input('start_time')) && $attendance->check_in_at) {
            $fallback['start_time'] = Carbon::parse($attendance->check_in_at)->format('H:i');
        }

        if (blank($this->input('end_time')) && $attendance->check_out_at) {
            $fallback['end_time'] = Carbon::parse($attendance->check_out_at)->format('H:i');
        }

        if (! empty($fallback)) {
            $this->merge($fallback);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateWorkTimeOrder($validator);
            $this->validateBreakRows($validator);
        });
    }

    private function validateWorkTimeOrder(Validator $validator): void
    {
        $start = $this->parseHm($this->input('start_time'));
        $end = $this->parseHm($this->input('end_time'));

        if ($start && $end && $start->gt($end)) {
            $validator->errors()->add(
                'start_time',
                $this->routeIs('admin.attendance.update')
                    ? '出勤時間もしくは退勤時間が不適切な値です'
                    : '出勤時間が不適切な値です'
            );
        }
    }

    private function validateBreakRows(Validator $validator): void
    {
        $starts = is_array($this->input('break_start_at')) ? $this->input('break_start_at') : [];
        $ends = is_array($this->input('break_end_at')) ? $this->input('break_end_at') : [];
        $rowCount = max(count($starts), count($ends));
        $workStart = $this->parseHm($this->input('start_time'));
        $workEnd = $this->parseHm($this->input('end_time'));

        for ($index = 0; $index < $rowCount; $index++) {
            $start = $this->parseHm($starts[$index] ?? null);
            $end = $this->parseHm($ends[$index] ?? null);

            if (! $start && $end) {
                $validator->errors()->add("break_end_at.{$index}", '休憩終了時刻だけは入力できません');
            }

            if ($start && $this->isOutsideWorkTime($start, $workStart, $workEnd)) {
                $validator->errors()->add("break_start_at.{$index}", '休憩時間が不適切な値です');
            }

            if (! $end) {
                continue;
            }

            if ($workStart && $end->lt($workStart)) {
                $validator->errors()->add("break_end_at.{$index}", '休憩時間が不適切な値です');
            }

            if ($workEnd && $end->gt($workEnd)) {
                $validator->errors()->add("break_end_at.{$index}", '休憩時間もしくは退勤時間が不適切な値です');
            }
        }

        for ($index = 1; $index < $rowCount; $index++) {
            $currentStart = $this->parseHm($starts[$index] ?? null);
            $currentEnd = $this->parseHm($ends[$index] ?? null);

            if (! $currentStart || ! $currentEnd) {
                continue;
            }

            for ($previousIndex = 0; $previousIndex < $index; $previousIndex++) {
                $previousStart = $this->parseHm($starts[$previousIndex] ?? null);
                $previousEnd = $this->parseHm($ends[$previousIndex] ?? null);

                if (! $previousStart || ! $previousEnd) {
                    continue;
                }

                $overlapsPreviousBreak = $currentStart->lt($previousEnd) && $currentEnd->gt($previousStart);
                if ($overlapsPreviousBreak) {
                    $validator->errors()->add("break_start_at.{$index}", '休憩時間が他の休憩と重複しています');
                    break;
                }
            }
        }
    }

    private function isOutsideWorkTime(Carbon $time, ?Carbon $workStart, ?Carbon $workEnd): bool
    {
        return ($workStart && $time->lt($workStart)) || ($workEnd && $time->gt($workEnd));
    }

    private function parseHm($value): ?Carbon
    {
        if (! is_string($value) || blank($value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('H:i', $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
