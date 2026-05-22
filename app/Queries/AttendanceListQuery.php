<?php

namespace App\Queries;

use App\Models\Attendance;
use App\Services\DurationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AttendanceListQuery
{
    public function __construct(private DurationService $durationService) {}

    // 指定日の勤怠をユーザー順で取得し、各行へ勤務時間情報を付与して返す。
    public function forDay(CarbonInterface $date): Collection
    {

        $dailyAttendances = Attendance::query()
            ->with('user', 'attendanceBreaks')
            ->whereDate('work_date', $date->toDateString())
            ->orderBy('user_id')
            ->get();

        $dailyAttendances->each(fn ($attendance) => $this->durationService->attachCalculatedDurations($attendance));

        return $dailyAttendances;
    }

    // 指定ユーザーの月次勤怠を取得し、欠損日を空データで補完して返す。
    public function forUserMonth(
        int $userId,
        CarbonInterface $month,
        bool $loadUser = false
    ): Collection {

        $monthStartDate = Carbon::parse($month)->startOfMonth();

        $monthEndDate = Carbon::parse($month)->endOfMonth();

        $relations = $loadUser ? ['user', 'attendanceBreaks'] : ['attendanceBreaks'];

        $attendances = Attendance::query()
            ->with($relations)
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$monthStartDate->toDateString(), $monthEndDate->toDateString()])
            ->orderBy('work_date')
            ->get();

        $attendancesByDate = $attendances->keyBy(
            fn ($attendance) => Carbon::parse($attendance->work_date)->toDateString()
        );

        $monthlyAttendances = collect();

        $currentDate = $monthStartDate->copy();

        while ($currentDate->lte($monthEndDate)) {

            $workDate = $currentDate->toDateString();

            $attendance = $attendancesByDate->get($workDate);

            if (! $attendance) {
                $attendance = new Attendance(['user_id' => $userId, 'work_date' => $workDate]);
                $attendance->setRelation('attendanceBreaks', collect());
            }

            $this->durationService->attachCalculatedDurations($attendance);

            $monthlyAttendances->push($attendance);

            $currentDate->addDay();
        }

        return $monthlyAttendances;
    }
}
