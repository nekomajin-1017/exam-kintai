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
    // その時点の日時を取得し、押されたボタンに合わせて処理を呼び出す。
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

    // 今日の勤怠を用意し、まだ出勤していないときだけ出勤時刻を設定。
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

    // 勤務中のデータだけを対象にして、退勤時刻を設定。
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

    // 休憩開始時刻を追加し、状態を「休憩中」に。
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

    // まだ終わっていない最新の休憩に終了時刻を入れ、状態を「勤務中」へ更新。
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

    // まず今日の勤怠を探し、なければ退勤していない直近の勤怠を使用。
    private function attendanceForOpenShiftAction(int $userId, string $workDate): ?Attendance
    {
        return $this->todayAttendance($userId, $workDate)
            ?? Attendance::query()
                ->where('user_id', $userId)
                ->whereNull('check_out_at')
                ->latest('work_date')
                ->first();
    }

    // ユーザーIDと勤務日で探し、最初の1件を返す。
    private function todayAttendance(int $userId, string $workDate): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $userId)
            ->whereDate('work_date', $workDate)
            ->first();
    }

    // 勤怠本体と休憩の申請データを作って保存し、作成結果を返す。
    public function requestCorrection(Attendance $attendance, int $requestUserId, array $payload): AttendanceCorrection
    {
        $baseDate = $this->baseDate($attendance);
        $breakRows = $this->requestBreakRows($baseDate, $payload);
        $correction = $this->createCorrection($attendance, $requestUserId, $payload, $baseDate);
        $this->createBreakCorrections($correction, $breakRows);

        return $correction;
    }

    // 申請内容を勤怠に反映し、状態を計算し直して承認情報を保存。
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

    // 出退勤と理由を更新し、休憩を入れ直して状態を再計算。
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

    // work_date を YYYY-MM-DD 形式の文字列へ変換して返す。
    private function baseDate(Attendance $attendance): string
    {
        return CarbonImmutable::parse($attendance->work_date)->format('Y-m-d');
    }

    // 休憩の入力値を整えて、日付と時刻を結合した配列に変換。
    private function requestBreakRows(string $baseDate, array $payload): array
    {
        return $this->toDateTimeRows(
            $baseDate,
            $this->normalizeBreakRows($payload['break_start_at'] ?? [], $payload['break_end_at'] ?? [])
        );
    }

    // 入力値があればそれを使い、なければ今の値を使って申請を保存。
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

    // 休憩行があるときだけ、まとめて保存。
    private function createBreakCorrections(AttendanceCorrection $correction, array $breakRows): void
    {
        if (! empty($breakRows)) {
            $correction->breakCorrections()->createMany($breakRows);
        }
    }

    // 申請で値が入っている項目だけ上書き。
    private function applyAttendanceCorrection(Attendance $attendance, AttendanceCorrection $correction): void
    {
        $attendance->update([
            'check_in_at' => $correction->requested_check_in_at ?? $attendance->check_in_at,
            'check_out_at' => $correction->requested_check_out_at ?? $attendance->check_out_at,
            'remarks' => $correction->reason ?? $attendance->remarks,
        ]);
    }

    // 休憩申請があるときだけ、今の休憩を消して申請内容で作り直し。
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

    // 開始と終了の日時を時刻文字列へ変換し、配列で返す。
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

    // 出勤・退勤・休憩の状況を見て状態コードを返す。
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

    // 開始が空の行を捨て、終了が空なら null に。
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

    // 基準日と start/end をつなげて保存用配列を作成。
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
