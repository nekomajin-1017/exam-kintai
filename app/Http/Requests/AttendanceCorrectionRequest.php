<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use App\Rules\BreakEndWithinShiftRule;
use App\Rules\BreakStartWithinShiftRule;
use App\Rules\WorkTimeOrderRule;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceCorrectionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'start_time' => ['nullable', 'date_format:H:i', new WorkTimeOrderRule],
            'end_time' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:1000'],
            'break_start_at' => ['nullable', 'array'],
            'break_start_at.*' => ['nullable', 'date_format:H:i', new BreakStartWithinShiftRule],
            'break_end_at' => ['nullable', 'array'],
            'break_end_at.*' => ['nullable', 'date_format:H:i', new BreakEndWithinShiftRule],
        ];
    }

    public function messages()
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
        /** @var Attendance|null $attendance */
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
            // 休憩開始/終了の再入力値を配列として受け取る。
            $starts = is_array($this->input('break_start_at')) ? $this->input('break_start_at') : [];
            $ends = is_array($this->input('break_end_at')) ? $this->input('break_end_at') : [];
            // 片側だけ増えても全行を確認できるように最大件数を使う。
            $rowCount = max(count($starts), count($ends));

            // 終了時刻のみ入力された行をエラーにする。
            for ($index = 0; $index < $rowCount; $index++) {
                $start = $starts[$index] ?? null;
                $end = $ends[$index] ?? null;

                if (blank($start) && filled($end)) {
                    $validator->errors()->add("break_end_at.{$index}", '休憩終了時刻だけは入力できません');
                }
            }

            // 休憩2以降は、それ以前の休憩すべてと重複不可。
            for ($index = 1; $index < $rowCount; $index++) {
                $currentStart = $this->parseHm($starts[$index] ?? null);
                $currentEnd = $this->parseHm($ends[$index] ?? null);

                // 開始/終了が揃っている行だけ重複判定する。
                if (! $currentStart || ! $currentEnd) {
                    continue;
                }

                for ($previousIndex = 0; $previousIndex < $index; $previousIndex++) {
                    $previousStart = $this->parseHm($starts[$previousIndex] ?? null);
                    $previousEnd = $this->parseHm($ends[$previousIndex] ?? null);

                    // 比較対象の前行が未入力ならスキップ。
                    if (! $previousStart || ! $previousEnd) {
                        continue;
                    }

                    // [start, end) 同士で重複を判定する。
                    $overlapsPreviousBreak = $currentStart->lt($previousEnd) && $currentEnd->gt($previousStart);
                    if ($overlapsPreviousBreak) {
                        $validator->errors()->add("break_start_at.{$index}", '休憩時間が他の休憩と重複しています');
                        break;
                    }
                }
            }
        });
    }

    private function parseHm(mixed $value): ?Carbon
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
