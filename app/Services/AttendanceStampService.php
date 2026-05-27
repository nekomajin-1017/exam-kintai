<?php

namespace App\Services;

use App\Constants\AttendanceStatusCode;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

class AttendanceStampService
{
    // 打刻種別に応じて当日の打刻処理を実行。
    public function stamp(int $userId, string $action): void
    {
        $now = CarbonImmutable::now();
        $workDate = $now->toDateString();

        match ($action) {
            'check_in' => $this->checkIn($userId, $workDate, $now),
            'check_out' => $this->checkOut($userId, $workDate, $now),
            'break_in' => $this->breakIn($userId, $workDate, $now),
            'break_out' => $this->breakOut($userId, $workDate, $now),
            default => null,
        };
    }

    // 出勤打刻を反映し、二重送信時は一意制約競合を吸収。
    private function checkIn(int $userId, string $workDate, CarbonImmutable $now): void
    {
        try {
            $attendance = Attendance::query()->firstOrCreate([
                'user_id' => $userId,
                'work_date' => $workDate,
            ]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }
            $attendance = $this->todayAttendance($userId, $workDate);
            if (! $attendance) {
                throw $exception;
            }
        }

        if ($attendance->attendance_status_code !== null
            && $attendance->attendance_status_code !== AttendanceStatusCode::OFF) {
            return;
        }

        $attendance->update([
            'check_in_at' => $now,
            'attendance_status_code' => AttendanceStatusCode::WORKING,
        ]);
    }

    // 退勤打刻を反映。
    private function checkOut(int $userId, string $workDate, CarbonImmutable $now): void
    {
        $attendance = $this->todayAttendance($userId, $workDate);
        if (! $attendance || $attendance->attendance_status_code !== AttendanceStatusCode::WORKING) {
            return;
        }

        $attendance->update([
            'check_out_at' => $now,
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
        ]);
    }

    // 休憩開始打刻を反映。
    private function breakIn(int $userId, string $workDate, CarbonImmutable $now): void
    {
        $attendance = $this->todayAttendance($userId, $workDate);
        if (! $attendance || $attendance->attendance_status_code !== AttendanceStatusCode::WORKING) {
            return;
        }

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_start_at' => $now,
        ]);

        $attendance->update(['attendance_status_code' => AttendanceStatusCode::ON_BREAK]);
    }

    // 休憩終了打刻を反映。
    private function breakOut(int $userId, string $workDate, CarbonImmutable $now): void
    {
        $attendance = $this->todayAttendance($userId, $workDate);
        if (! $attendance || $attendance->attendance_status_code !== AttendanceStatusCode::ON_BREAK) {
            return;
        }

        $openBreak = AttendanceBreak::query()
            ->where('attendance_id', $attendance->id)
            ->whereNull('break_end_at')
            ->latest('break_start_at')
            ->first();

        if (! $openBreak) {
            return;
        }

        $openBreak->update(['break_end_at' => $now]);
        $attendance->update(['attendance_status_code' => AttendanceStatusCode::WORKING]);
    }

    // 指定ユーザーの当日勤怠を取得。
    private function todayAttendance(int $userId, string $workDate): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $userId)
            ->whereDate('work_date', $workDate)
            ->first();
    }
}
