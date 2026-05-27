<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class DurationService
{
    // 秒数を HH:MM 形式の文字列に変換。
    public static function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    // 勤怠へ休憩時間と合計勤務時間の計算結果をセット。
    public function attachCalculatedDurations($attendance): void
    {

        $breakSeconds = $this->breakSeconds($attendance);

        $totalSeconds = $this->workSeconds($attendance, $breakSeconds);

        $attendance->setAttribute('calculated_break_seconds', $breakSeconds);

        $attendance->setAttribute('calculated_total_seconds', $totalSeconds);
    }

    // 休憩行の開始・終了をもとに休憩秒数を合計して返す。
    public function breakSeconds($attendance): int
    {

        $checkIn = $this->combineWorkDateAndTime($attendance, $attendance->check_in_at);

        $checkOut = $this->combineWorkDateAndTime($attendance, $attendance->check_out_at);

        return (int) $attendance->attendanceBreaks->sum(function ($attendanceBreak) use ($attendance, $checkIn, $checkOut) {

            if (! $attendanceBreak->break_start_at) {
                return 0;
            }

            $breakStartAt = $this->combineWorkDateAndTime($attendance, $attendanceBreak->break_start_at);

            $breakEndAt = $attendanceBreak->break_end_at ? $this->combineWorkDateAndTime($attendance, $attendanceBreak->break_end_at) : ($checkOut ? $checkOut->copy() : null);

            if (! $breakStartAt || ! $breakEndAt || $breakEndAt->lte($breakStartAt)) {
                return 0;
            }

            if ($checkIn && $checkOut) {

                $clampedStartAt = $breakStartAt->lt($checkIn) ? $checkIn : $breakStartAt;

                $clampedEndAt = $breakEndAt->gt($checkOut) ? $checkOut : $breakEndAt;

                if ($clampedEndAt->lte($clampedStartAt)) {
                    return 0;
                }

                return $clampedStartAt->diffInSeconds($clampedEndAt);
            }

            return $breakStartAt->diffInSeconds($breakEndAt);
        });
    }

    // 出勤から退勤までの秒数から休憩秒数を引いた勤務秒数を返す。
    public function workSeconds($attendance, int $breakSeconds): int
    {

        $checkIn = $this->combineWorkDateAndTime($attendance, $attendance->check_in_at);

        $checkOut = $this->combineWorkDateAndTime($attendance, $attendance->check_out_at);

        if (! $checkIn || ! $checkOut || $checkOut->lte($checkIn)) {
            return 0;
        }

        return max(0, $checkIn->diffInSeconds($checkOut) - (int) $breakSeconds);
    }

    // 日時を勤務日の日付に合わせた Carbon へ変換。
    private function combineWorkDateAndTime($attendance, $dateTime): ?CarbonInterface
    {

        if (! $dateTime) {
            return null;
        }

        $baseDate = Carbon::parse($attendance->work_date)->format('Y-m-d');

        $timePart = Carbon::parse($dateTime)->format('H:i:s');

        return Carbon::parse($baseDate.' '.$timePart);
    }
}
