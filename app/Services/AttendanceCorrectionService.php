<?php

namespace App\Services;

use App\Constants\ApprovalStatusCode;
use App\Constants\AttendanceStatusCode;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use Carbon\CarbonImmutable;

class AttendanceCorrectionService
{
    // 勤怠修正申請を作成し、休憩修正行も保存。
    public function requestCorrection(Attendance $attendance, int $requestUserId, array $correctionInput): AttendanceCorrection
    {
        $workDateYmd = $this->workDateYmd($attendance);
        $breakRows = $this->buildBreakDateTimeRowsFromInput($workDateYmd, $correctionInput);
        $correction = $this->createCorrection($attendance, $requestUserId, $correctionInput, $workDateYmd);
        if (! empty($breakRows)) {
            $correction->breakCorrections()->createMany($breakRows);
        }

        return $correction;
    }

    // 修正申請を承認し、勤怠本体へ反映。
    public function approveCorrection(AttendanceCorrection $correction, int $adminUserId): void
    {
        $correction->load('attendance');
        $attendance = $correction->attendance;

        $this->applyAttendanceCorrection($attendance, $correction);
        $this->replaceBreaksFromCorrection($attendance, $correction);
        $attendance->update([
            'attendance_status_code' => $this->determineAttendanceStatusCode($attendance),
        ]);

        $correction->update([
            'approval_status_code' => ApprovalStatusCode::APPROVED,
            'approved_by' => $adminUserId,
            'approved_at' => now(),
        ]);
    }

    // 管理者による勤怠直接修正を反映。
    public function updateAttendance(Attendance $attendance, array $correctionInput): void
    {
        $workDateYmd = $this->workDateYmd($attendance);

        $attendance->update([
            'check_in_at' => ! empty($correctionInput['start_time'])
                ? CarbonImmutable::parse($workDateYmd.' '.$correctionInput['start_time'])
                : null,
            'check_out_at' => ! empty($correctionInput['end_time'])
                ? CarbonImmutable::parse($workDateYmd.' '.$correctionInput['end_time'])
                : null,
            'remarks' => $correctionInput['reason'] ?? null,
        ]);

        $breakRows = $this->buildBreakDateTimeRowsFromInput($workDateYmd, $correctionInput);

        $attendance->attendanceBreaks()->delete();
        if (! empty($breakRows)) {
            $attendance->attendanceBreaks()->createMany($breakRows);
        }

        $attendance->update([
            'attendance_status_code' => $this->determineAttendanceStatusCode($attendance, $breakRows),
        ]);
    }

    // 勤務日の基準日文字列を返す。
    private function workDateYmd(Attendance $attendance): string
    {
        return CarbonImmutable::parse($attendance->work_date)->format('Y-m-d');
    }

    // 入力済み休憩行を保存用日時配列へ変換。
    private function buildBreakDateTimeRowsFromInput(string $workDateYmd, array $correctionInput): array
    {
        return $this->toDateTimeRows(
            $workDateYmd,
            $this->normalizeBreakRows($correctionInput['break_start_at'] ?? [], $correctionInput['break_end_at'] ?? []),
        );
    }

    // 修正申請本体レコードを作成。
    private function createCorrection(
        Attendance $attendance,
        int $requestUserId,
        array $correctionInput,
        string $workDateYmd
    ): AttendanceCorrection {
        return AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'request_user_id' => $requestUserId,
            'requested_check_in_at' => isset($correctionInput['start_time'])
                ? CarbonImmutable::parse($workDateYmd.' '.$correctionInput['start_time'])
                : $attendance->check_in_at,
            'requested_check_out_at' => isset($correctionInput['end_time'])
                ? CarbonImmutable::parse($workDateYmd.' '.$correctionInput['end_time'])
                : $attendance->check_out_at,
            'reason' => $correctionInput['reason'] ?? null,
            'approval_status_code' => ApprovalStatusCode::PENDING,
        ]);
    }

    // 修正申請の出退勤・備考を勤怠本体へ適用。
    private function applyAttendanceCorrection(Attendance $attendance, AttendanceCorrection $correction): void
    {
        $attendance->update([
            'check_in_at' => $correction->requested_check_in_at ?? $attendance->check_in_at,
            'check_out_at' => $correction->requested_check_out_at ?? $attendance->check_out_at,
            'remarks' => $correction->reason ?? $attendance->remarks,
        ]);
    }

    // 修正申請に休憩行がある場合は勤怠休憩行を置換。
    private function replaceBreaksFromCorrection(Attendance $attendance, AttendanceCorrection $correction): void
    {
        if (! $correction->breakCorrections()->exists()) {
            return;
        }

        $breakRows = $this->toDateTimeRows(
            $this->workDateYmd($attendance),
            $this->breakRowsFromCorrections($correction->breakCorrections()->orderBy('break_start_at')->get())
        );

        $attendance->attendanceBreaks()->delete();
        if (! empty($breakRows)) {
            $attendance->attendanceBreaks()->createMany($breakRows);
        }
    }

    // 休憩修正行を start/end 形式へ整形。
    private function breakRowsFromCorrections($breakCorrections): array
    {
        $breakStartTimes = [];
        $breakEndTimes = [];

        foreach ($breakCorrections as $breakCorrectionRow) {
            $breakStartTimes[] = $breakCorrectionRow->break_start_at
                ? CarbonImmutable::parse($breakCorrectionRow->break_start_at)->format('H:i:s')
                : null;
            $breakEndTimes[] = $breakCorrectionRow->break_end_at
                ? CarbonImmutable::parse($breakCorrectionRow->break_end_at)->format('H:i:s')
                : null;
        }

        return $this->normalizeBreakRows($breakStartTimes, $breakEndTimes);
    }

    // 出退勤と休憩情報から勤務ステータスを判定。
    private function determineAttendanceStatusCode(Attendance $attendance, ?array $breakRows = null): string
    {
        if (! $attendance->check_in_at) {
            return AttendanceStatusCode::OFF;
        }

        if ($attendance->check_out_at) {
            return AttendanceStatusCode::FINISHED;
        }

        $hasOpenBreak = $breakRows !== null
            ? collect($breakRows)->contains(fn (array $row) => empty($row['break_end_at']))
            : $attendance->attendanceBreaks()->whereNull('break_end_at')->exists();

        return $hasOpenBreak ? AttendanceStatusCode::ON_BREAK : AttendanceStatusCode::WORKING;
    }

    // 入力休憩行の有効な start/end だけを抽出して整形。
    private function normalizeBreakRows(array $breakStartTimes, array $breakEndTimes): array
    {
        $normalizedBreakRows = [];

        for ($rowIndex = 0, $rowCount = max(count($breakStartTimes), count($breakEndTimes)); $rowIndex < $rowCount; $rowIndex++) {
            $breakStartAt = $breakStartTimes[$rowIndex] ?? null;
            if (blank($breakStartAt)) {
                continue;
            }

            $breakEndAt = $breakEndTimes[$rowIndex] ?? null;
            $normalizedBreakRows[] = [
                'start' => $breakStartAt,
                'end' => blank($breakEndAt) ? null : $breakEndAt,
            ];
        }

        return $normalizedBreakRows;
    }

    // start/end 行を基準日付き日時配列へ変換。
    private function toDateTimeRows(string $baseDate, array $breakRows): array
    {
        $breakDateTimeRows = [];

        foreach ($breakRows as $breakRow) {
            $breakDateTimeRows[] = [
                'break_start_at' => CarbonImmutable::parse($baseDate.' '.$breakRow['start'])->toDateTimeString(),
                'break_end_at' => ! blank($breakRow['end'])
                    ? CarbonImmutable::parse($baseDate.' '.$breakRow['end'])->toDateTimeString()
                    : null,
            ];
        }

        return $breakDateTimeRows;
    }
}
