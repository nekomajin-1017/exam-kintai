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

    private function checkOut(int $userId, string $workDate, CarbonImmutable $now): void
    {
        $attendance = $this->attendanceForOpenShiftAction($userId, $workDate);
        if (! $attendance || ! in_array($attendance->attendance_status_code, [
            AttendanceStatusCode::WORKING,
            AttendanceStatusCode::ON_BREAK,
        ], true)) {
            return;
        }

        AttendanceBreak::query()
            ->where('attendance_id', $attendance->id)
            ->whereNull('break_end_at')
            ->update(['break_end_at' => $now]);

        $attendance->update([
            'check_out_at' => $now,
            'attendance_status_code' => AttendanceStatusCode::FINISHED,
        ]);
    }

    private function breakIn(int $userId, string $workDate, CarbonImmutable $now): void
    {
        $attendance = $this->attendanceForOpenShiftAction($userId, $workDate);
        if (! $attendance || $attendance->attendance_status_code !== AttendanceStatusCode::WORKING) {
            return;
        }

        AttendanceBreak::firstOrCreate(
            ['attendance_id' => $attendance->id, 'break_end_at' => null],
            ['break_start_at' => $now]
        );

        $attendance->update(['attendance_status_code' => AttendanceStatusCode::ON_BREAK]);
    }

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

        if ($openBreak) {
            $openBreak->update(['break_end_at' => $now]);
        }

        $attendance->update(['attendance_status_code' => AttendanceStatusCode::WORKING]);
    }

    private function attendanceForOpenShiftAction(int $userId, string $workDate): ?Attendance
    {
        return $this->todayAttendance($userId, $workDate)
            ?? Attendance::query()
                ->where('user_id', $userId)
                ->whereNull('check_out_at')
                ->latest('work_date')
                ->first();
    }

    private function todayAttendance(int $userId, string $workDate): ?Attendance
    {
        return Attendance::query()
            ->where('user_id', $userId)
            ->whereDate('work_date', $workDate)
            ->first();
    }

    public function requestCorrection(Attendance $attendance, int $requestUserId, array $payload): AttendanceCorrection
    {
        $baseDate = $this->baseDate($attendance);
        $breakRows = $this->requestBreakRows($baseDate, $payload);
        $correction = $this->createCorrection($attendance, $requestUserId, $payload, $baseDate);
        $this->createBreakCorrections($correction, $breakRows);

        return $correction;
    }

    public function approveCorrection(AttendanceCorrection $correction, int $adminUserId): void
    {
        $correction->load('attendance');
        $attendance = $correction->attendance;

        $this->applyAttendanceCorrection($attendance, $correction);
        $this->replaceBreaksFromCorrection($attendance, $correction);

        $correction->update([
            'approval_status_code' => ApprovalStatusCode::APPROVED,
            'approved_by' => $adminUserId,
            'approved_at' => now(),
        ]);
    }

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
    }

    private function baseDate(Attendance $attendance): string
    {
        return CarbonImmutable::parse($attendance->work_date)->format('Y-m-d');
    }

    private function requestBreakRows(string $baseDate, array $payload): array
    {
        return $this->toDateTimeRows(
            $baseDate,
            $this->normalizeBreakRows($payload['break_start_at'] ?? [], $payload['break_end_at'] ?? [])
        );
    }

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

    private function createBreakCorrections(AttendanceCorrection $correction, array $breakRows): void
    {
        if (! empty($breakRows)) {
            $correction->breakCorrections()->createMany($breakRows);
        }
    }

    private function applyAttendanceCorrection(Attendance $attendance, AttendanceCorrection $correction): void
    {
        $attendance->update([
            'check_in_at' => $correction->requested_check_in_at ?? $attendance->check_in_at,
            'check_out_at' => $correction->requested_check_out_at ?? $attendance->check_out_at,
            'remarks' => $correction->reason ?? $attendance->remarks,
        ]);
    }

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

    private function breakRowsFromCorrections(iterable $breakCorrections): array
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
