<?php

namespace App\Services;

use Carbon\Carbon;

class DurationService
{
    public function attach($attendance)
    {
        // 休憩秒数を計算する。
        $breakSeconds = $this->breakSeconds($attendance);
        // 労働秒数を計算する。
        $totalSeconds = $this->workSeconds($attendance, $breakSeconds);
        // 表示用属性へ付与する。
        $attendance->setAttribute('calculated_break_seconds', $breakSeconds);
        // 表示用属性へ付与する。
        $attendance->setAttribute('calculated_total_seconds', $totalSeconds);
    }

    public function breakSeconds($attendance)
    {
        // 出勤時刻を勤務日基準に正規化する。
        $checkIn = $this->toWorkDate($attendance, $attendance->check_in_at);
        // 退勤時刻を勤務日基準に正規化する。
        $checkOut = $this->toWorkDate($attendance, $attendance->check_out_at);

        // 無効区間を除外しながら休憩秒数を合算する。
        return (int) $attendance->breaks->sum(function ($break) use ($attendance, $checkIn, $checkOut) {
            // 開始なしは無効。
            if (! $break->break_start_at) return 0;
            // 開始時刻を勤務日基準へ正規化する。
            $start = $this->toWorkDate($attendance, $break->break_start_at);
            // 終了未入力は退勤時刻を仮終了に使う。
            $end = $break->break_end_at ? $this->toWorkDate($attendance, $break->break_end_at) : ($checkOut ? $checkOut->copy() : null);
            // 不正区間は無効。
            if (! $start || ! $end || $end->lte($start)) return 0;
            // 出退勤がある場合は勤務時間外を切り捨てる。
            if ($checkIn && $checkOut) {
                // 休憩開始は出勤時刻より前に出さない。
                $effectiveStart = $start->lt($checkIn) ? $checkIn : $start;
                // 休憩終了は退勤時刻より後に出さない。
                $effectiveEnd = $end->gt($checkOut) ? $checkOut : $end;
                // 補正後に逆転すれば無効。
                if ($effectiveEnd->lte($effectiveStart)) return 0;
                // 有効区間の秒数。
                return $effectiveStart->diffInSeconds($effectiveEnd);
            }
            // 出退勤不明なら単純差分を採用する。
            return $start->diffInSeconds($end);
        });
    }

    public function workSeconds($attendance, $breakSeconds)
    {
        // 出勤時刻を勤務日基準に正規化する。
        $checkIn = $this->toWorkDate($attendance, $attendance->check_in_at);
        // 退勤時刻を勤務日基準に正規化する。
        $checkOut = $this->toWorkDate($attendance, $attendance->check_out_at);
        // 出退勤欠落または逆転は0秒。
        if (! $checkIn || ! $checkOut || $checkOut->lte($checkIn)) return 0;
        // 実労働秒数 = 在席秒数 - 休憩秒数（下限0）。
        return max(0, $checkIn->diffInSeconds($checkOut) - (int) $breakSeconds);
    }

    private function toWorkDate($attendance, $dateTime)
    {
        // 空値は null。
        if (! $dateTime) return null;
        // 勤務日の年月日。
        $baseDate = Carbon::parse($attendance->work_date)->format('Y-m-d');
        // 時刻部分。
        $timePart = Carbon::parse($dateTime)->format('H:i:s');
        // 勤務日+時刻として返す。
        return Carbon::parse($baseDate.' '.$timePart);
    }
}
