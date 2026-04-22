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
        });
    }
}
