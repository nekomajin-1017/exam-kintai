<?php

namespace Database\Seeders;

use App\Constants\ApprovalStatusCode;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\BreakCorrection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class AttendanceCorrectionSeeder extends Seeder
{
    public function run(): void
    {
        $records = Attendance::query()->get();
        $admins = User::query()->where('is_admin', true)->get();

        $this->createPendingCorrections($records);
        $this->createApprovedCorrections($records, $admins);
    }

    private function createPendingCorrections(Collection $records): void
    {
        foreach ($records->random(min(20, $records->count())) as $attendance) {
            $correction = AttendanceCorrection::factory()->create(
                $this->correctionAttrs($attendance, ApprovalStatusCode::PENDING)
            );
            $this->breakRows($attendance, $correction);
        }
    }

    private function createApprovedCorrections(Collection $records, Collection $admins): void
    {
        foreach ($records->random(min(20, $records->count())) as $attendance) {
            $correction = AttendanceCorrection::factory()->create(
                $this->correctionAttrs($attendance, ApprovalStatusCode::APPROVED, $admins->random()->id)
            );
            $this->breakRows($attendance, $correction);
        }
    }

    private function correctionAttrs(Attendance $attendance, string $statusCode, ?int $approvedBy = null): array
    {
        return [
            'attendance_id' => $attendance->id,
            'request_user_id' => $attendance->user_id,
            'requested_check_in_at' => $attendance->check_in_at?->copy()->addMinutes(random_int(-30, 30)),
            'requested_check_out_at' => $attendance->check_out_at?->copy()->addMinutes(random_int(-30, 30)),
            'approval_status_code' => $statusCode,
            'approved_by' => $approvedBy,
            'approved_at' => $approvedBy ? now()->subDays(random_int(0, 30)) : null,
        ];
    }

    private function breakRows(Attendance $attendance, AttendanceCorrection $correction): void
    {
        $baseDate = Carbon::parse($attendance->work_date)->format('Y-m-d');

        foreach ($attendance->breaks()->orderBy('break_start_at')->get() as $break) {
            $startTime = $break->break_start_at?->format('H:i:s');
            if (! $startTime) {
                continue;
            }

            $startAt = Carbon::parse($baseDate.' '.$startTime)->addMinutes(random_int(-10, 10));
            $endAt = $break->break_end_at
                ? Carbon::parse($baseDate.' '.$break->break_end_at->format('H:i:s'))->addMinutes(random_int(-10, 10))
                : null;

            if ($endAt && $endAt->lte($startAt)) {
                $endAt = $startAt->copy()->addMinutes(5);
            }

            BreakCorrection::create([
                'attendance_correction_id' => $correction->id,
                'break_start_at' => $startAt,
                'break_end_at' => $endAt,
            ]);
        }
    }
}
