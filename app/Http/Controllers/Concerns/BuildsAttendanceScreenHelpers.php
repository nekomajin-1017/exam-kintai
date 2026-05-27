<?php

namespace App\Http\Controllers\Concerns;

use App\Constants\AttendanceStatusCode;
use App\Http\Controllers\Concerns\BuildsAttendanceViewData;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

trait BuildsAttendanceScreenHelpers
{
    use BuildsAttendanceViewData;

    protected function renderAttendanceDetail(
        Attendance $targetAttendance,
        ?string $formAction,
        ?string $submitLabel,
        bool $readonly = false,
        bool $submitDisabled = false,
        ?string $statusMessage = null,
    ): View {
        $targetAttendance->load('user', 'attendanceBreaks');
        $attendanceBreaks = $targetAttendance->attendanceBreaks()->orderBy('break_start_at')->get();
        $detailFields = $this->buildAttendanceDetailFields(
            $targetAttendance,
            $attendanceBreaks,
            $readonly
        );

        return view('attendance_detail_screen', [
            'detailFields' => $detailFields,
            'formAction' => $formAction,
            'formMethod' => 'PUT',
            'submitLabel' => $submitLabel,
            'submitDisabled' => $submitDisabled,
            'statusMessage' => $statusMessage,
        ]);
    }

    protected function validateWorkDateOrAbort(string $date): string
    {
        $validatedWorkDate = Carbon::createFromFormat('Y-m-d', $date);
        if (! $validatedWorkDate || $validatedWorkDate->format('Y-m-d') !== $date) {
            abort(404);
        }

        return $validatedWorkDate->toDateString();
    }

    protected function findOrCreateAttendanceForDate(int $userId, string $workDate): Attendance
    {
        return Attendance::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'work_date' => $workDate,
            ],
            [
                'attendance_status_code' => AttendanceStatusCode::OFF,
            ]
        );
    }
}
