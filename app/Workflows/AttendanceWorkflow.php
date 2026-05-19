<?php

namespace App\Workflows;

use App\Constants\ApprovalStatusCode;
use App\Constants\AttendanceStatusCode;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\AttendanceCorrection;
use Carbon\CarbonImmutable;

class AttendanceWorkflow
{
    // 打刻種別に応じた勤怠更新処理の振り分け。
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

    // 出勤打刻の反映。
    private function checkIn(int $userId, string $workDate, CarbonImmutable $now): void
    {
        $attendance = $this->todayAttendance($userId, $workDate)
            ?? Attendance::create(['user_id' => $userId, 'work_date' => $workDate]);

        if ($attendance->attendance_status_code !== null
            && $attendance->attendance_status_code !== AttendanceStatusCode::OFF) {
            return;
        }

        $attendance->update([
            'check_in_at' => $now,
            'attendance_status_code' => AttendanceStatusCode::WORKING,
        ]);
    }

    // 退勤打刻の反映。
    private function checkOut(int $userId, string $workDate, CarbonImmutable $now): void
    {
        $attendance = $this->attendanceForOpenShiftAction($userId, $workDate);
        if (! $attendance || $attendance->attendance_status_code !== AttendanceStatusCode::WORKING) {
            return;
        }

        $attendance->update([
            'check_out_at' => $now,
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
        ]);
    }

    // 休憩開始打刻の反映。
    private function breakIn(int $userId, string $workDate, CarbonImmutable $now): void
    {
        $attendance = $this->attendanceForOpenShiftAction($userId, $workDate);
        if (! $attendance || $attendance->attendance_status_code !== AttendanceStatusCode::WORKING) {
            return;
        }

        AttendanceBreak::create([
            'attendance_id' => $attendance->id,
            'break_start_at' => $now,
        ]);

        $attendance->update(['attendance_status_code' => AttendanceStatusCode::ON_BREAK]);
    }

    // 休憩終了打刻の反映。
    private function breakOut(int $userId, string $workDate, CarbonImmutable $now): void
    {
        $attendance = $this->attendanceForOpenShiftAction($userId, $workDate);
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

    // 当日勤怠の取得。
    private function attendanceForOpenShiftAction(int $userId, string $workDate): ?Attendance
    {
        return $this->todayAttendance($userId, $workDate);
    }

    // 指定ユーザーの当日勤怠取得。
    private function todayAttendance(int $userId, string $workDate): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $userId)
            ->whereDate('work_date', $workDate)
            ->first();
    }

    // 修正申請本体と休憩申請行の作成。
    public function requestCorrection(Attendance $attendance, int $requestUserId, array $payload): AttendanceCorrection
    {
        $baseDate = $this->baseDate($attendance);
        $breakRows = $this->requestBreakRows($baseDate, $payload);
        $correction = $this->createCorrection($attendance, $requestUserId, $payload, $baseDate);
        if (! empty($breakRows)) {
            $correction->breakCorrections()->createMany($breakRows);
        }

        return $correction;
    }

    // 申請承認内容の勤怠本体反映、申請の承認済み更新。
    public function approveCorrection(AttendanceCorrection $correction, int $adminUserId): void
    {
        $correction->load('attendance');
        $attendance = $correction->attendance;

        $this->applyAttendanceCorrection($attendance, $correction);
        $this->replaceBreaksFromCorrection($attendance, $correction);
        $attendance->update([
            'attendance_status_code' => $this->resolveAttendanceStatusCode($attendance),
        ]);

        $correction->update([
            'approval_status_code' => ApprovalStatusCode::APPROVED,
            'approved_by' => $adminUserId,
            'approved_at' => now(),
        ]);
    }

    // 管理者による勤怠直接修正の反映。
    public function updateAttendance(Attendance $attendance, array $payload): void
    {
        $baseDate = $this->baseDate($attendance);

        $attendance->update([
            'check_in_at' => ! empty($payload['start_time'])
                ? CarbonImmutable::parse($baseDate.' '.$payload['start_time'])
                : null,
            'check_out_at' => ! empty($payload['end_time'])
                ? CarbonImmutable::parse($baseDate.' '.$payload['end_time'])
                : null,
            'remarks' => $payload['reason'] ?? null,
        ]);

        $breakRows = $this->requestBreakRows($baseDate, $payload);

        $attendance->breaks()->delete();
        if (! empty($breakRows)) {
            $attendance->breaks()->createMany($breakRows);
        }

        $attendance->update([
            'attendance_status_code' => $this->resolveAttendanceStatusCode($attendance, $breakRows),
        ]);
    }

    // 勤務日の基準日文字列を返す。
    private function baseDate(Attendance $attendance): string
    {
        return CarbonImmutable::parse($attendance->work_date)->format('Y-m-d');
    }

    // 入力済み休憩行の保存用日時配列への変換。
    private function requestBreakRows(string $baseDate, array $payload): array
    {
        return $this->toDateTimeRows(
            $baseDate,
            $this->normalizeBreakRows($payload['break_start_at'] ?? [], $payload['break_end_at'] ?? []),
        );
    }

    // 修正申請本体の作成。
    private function createCorrection(
        Attendance $attendance,
        int $requestUserId,
        array $payload,
        string $baseDate
    ): AttendanceCorrection {
        return AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'request_user_id' => $requestUserId,
            'requested_check_in_at' => isset($payload['start_time'])
                ? CarbonImmutable::parse($baseDate.' '.$payload['start_time'])
                : $attendance->check_in_at,
            'requested_check_out_at' => isset($payload['end_time'])
                ? CarbonImmutable::parse($baseDate.' '.$payload['end_time'])
                : $attendance->check_out_at,
            'reason' => $payload['reason'] ?? null,
            'approval_status_code' => ApprovalStatusCode::PENDING,
        ]);
    }

    // 申請本体の時刻・備考の勤怠本体適用。
    private function applyAttendanceCorrection(Attendance $attendance, AttendanceCorrection $correction): void
    {
        $attendance->update([
            'check_in_at' => $correction->requested_check_in_at ?? $attendance->check_in_at,
            'check_out_at' => $correction->requested_check_out_at ?? $attendance->check_out_at,
            'remarks' => $correction->reason ?? $attendance->remarks,
        ]);
    }

    // 休憩申請がある場合の休憩行置換。
    private function replaceBreaksFromCorrection(Attendance $attendance, AttendanceCorrection $correction): void
    {
        if (! $correction->breakCorrections()->exists()) {
            return;
        }

        $breakRows = $this->toDateTimeRows(
            $this->baseDate($attendance),
            $this->breakRowsFromCorrections($correction->breakCorrections()->orderBy('break_start_at')->get())
        );

        $attendance->breaks()->delete();

        if (! empty($breakRows)) {
            $attendance->breaks()->createMany($breakRows);
        }
    }

    // 休憩申請行の start/end 形式正規化。
    private function breakRowsFromCorrections($breakCorrections): array
    {
        $starts = [];
        $ends = [];

        foreach ($breakCorrections as $breakRow) {
            $starts[] = $breakRow->break_start_at
                ? CarbonImmutable::parse($breakRow->break_start_at)->format('H:i:s')
                : null;
            $ends[] = $breakRow->break_end_at
                ? CarbonImmutable::parse($breakRow->break_end_at)->format('H:i:s')
                : null;
        }

        return $this->normalizeBreakRows($starts, $ends);
    }

    // 出退勤と休憩状態からの勤務ステータス解決。
    private function resolveAttendanceStatusCode(Attendance $attendance, ?array $breakRows = null): string
    {
        if (! $attendance->check_in_at) {
            return AttendanceStatusCode::OFF;
        }

        if ($attendance->check_out_at) {
            return AttendanceStatusCode::FINISHED;
        }

        $hasOpenBreak = $breakRows !== null
            ? collect($breakRows)->contains(fn (array $row) => empty($row['break_end_at']))
            : $attendance->breaks()->whereNull('break_end_at')->exists();

        return $hasOpenBreak ? AttendanceStatusCode::ON_BREAK : AttendanceStatusCode::WORKING;
    }

    // 休憩開始・終了入力の有効行のみ正規化。
    private function normalizeBreakRows(array $starts, array $ends): array
    {
        $rows = [];

        for ($rowIndex = 0, $rowCount = max(count($starts), count($ends)); $rowIndex < $rowCount; $rowIndex++) {
            $startAt = $starts[$rowIndex] ?? null;
            if (blank($startAt)) {
                continue;
            }

            $endAt = $ends[$rowIndex] ?? null;
            $rows[] = [
                'start' => $startAt,
                'end' => blank($endAt) ? null : $endAt,
            ];
        }

        return $rows;
    }

    // start/end の基準日付き日時配列への変換。
    private function toDateTimeRows(string $baseDate, array $rows): array
    {
        $dateTimeRows = [];

        foreach ($rows as $row) {
            $dateTimeRows[] = [
                'break_start_at' => CarbonImmutable::parse($baseDate.' '.$row['start'])->toDateTimeString(),
                'break_end_at' => ! blank($row['end'])
                    ? CarbonImmutable::parse($baseDate.' '.$row['end'])->toDateTimeString()
                    : null,
            ];
        }

        return $dateTimeRows;
    }
}
