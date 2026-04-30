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

    public function forDay(CarbonInterface $date): Collection
    {

        $records = Attendance::query()
            ->with('user', 'breaks')
            ->whereDate('work_date', $date->toDateString())
            ->orderBy('user_id')
            ->get();

        $records->each(fn ($attendance) => $this->durationService->attach($attendance));

        return $records;
    }

    public function forUserMonth(
        int $userId,
        CarbonInterface $month,
        bool $withUser = false,
        bool $includeMissingDates = false
    ): Collection {

        $start = Carbon::parse($month)->startOfMonth();

        $end = Carbon::parse($month)->endOfMonth();

        $relations = $withUser ? ['user', 'breaks'] : ['breaks'];

        $records = Attendance::query()
            ->with($relations)
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->get();

        if (! $includeMissingDates) {
            $records->each(fn ($attendance) => $this->durationService->attach($attendance));

            return $records;
        }

        $indexed = $records->keyBy(
            fn ($attendance) => Carbon::parse($attendance->work_date)->toDateString()
        );

        $filled = collect();

        $cursor = $start->copy();

        while ($cursor->lte($end)) {

            $dateKey = $cursor->toDateString();

            $attendance = $indexed->get($dateKey);

            if (! $attendance) {
                $attendance = new Attendance(['user_id' => $userId, 'work_date' => $dateKey]);
                $attendance->setRelation('breaks', collect());
            }

            $this->durationService->attach($attendance);

            $filled->push($attendance);

            $cursor->addDay();
        }

        return $filled;
    }
}
