<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

trait BuildsAttendanceViewData
{
    protected function buildMonthNavigation(CarbonInterface $month, string $routeName, array $params = []): array
    {

        $current = $month->copy()->startOfMonth();
        $previous = $current->copy()->subMonth();
        $next = $current->copy()->addMonth();

        return [
            'previousUrl' => route($routeName, [...$params, 'month' => $previous->format('Y-m')]),
            'nextUrl' => route($routeName, [...$params, 'month' => $next->format('Y-m')]),
            'currentLabel' => $current->locale('ja')->isoFormat('Y年M月'),
            'previousLabel' => $previous->locale('ja')->isoFormat('Y年M月'),
            'nextLabel' => $next->locale('ja')->isoFormat('Y年M月'),
        ];
    }

    protected function buildDayNavigation(CarbonInterface $day, string $routeName, array $params = []): array
    {

        $current = $day->copy()->startOfDay();
        $previous = $current->copy()->subDay();
        $next = $current->copy()->addDay();

        return [
            'previousUrl' => route($routeName, [...$params, 'date' => $previous->toDateString()]),
            'nextUrl' => route($routeName, [...$params, 'date' => $next->toDateString()]),
            'currentLabel' => $current->locale('ja')->isoFormat('Y年MM月DD日'),
            'previousLabel' => $previous->locale('ja')->isoFormat('Y年MM月DD日'),
            'nextLabel' => $next->locale('ja')->isoFormat('Y年MM月DD日'),
        ];
    }

    protected function buildDetailFromCorrection(AttendanceCorrection $correction): array
    {

        $correction->load('attendance.user', 'attendance.breaks', 'breakCorrections');

        $attendance = $correction->attendance;
        $attendance->check_in_at = $correction->requested_check_in_at ?? $attendance->check_in_at;
        $attendance->check_out_at = $correction->requested_check_out_at ?? $attendance->check_out_at;
        $attendance->remarks = $correction->reason ?? $attendance->remarks;

        $breaks = $correction->breakCorrections->sortBy('break_start_at')->values();

        return [
            'attendance' => $attendance,
            'breaks' => $breaks,
        ];
    }

    protected function buildAttendanceDetailFields(
        Attendance $attendance,
        Collection|EloquentCollection|array $breaks,
        bool $readonly,
        bool $plainReadonly
    ): array {

        $breakRows = $this->resolveBreakRows($breaks);
        if (count($breakRows) === 0) {
            $breakRows[] = ['start' => '', 'end' => ''];
        }

        if (! $readonly) {
            $breakRows[] = ['start' => '', 'end' => ''];
        }

        return [
            'name' => $attendance->user->name,
            'workDateLabel' => Carbon::parse($attendance->work_date)->locale('ja')->isoFormat('Y年MM月DD日'),
            'startTime' => old('start_time', $this->formatHm($attendance->check_in_at)),
            'endTime' => old('end_time', $this->formatHm($attendance->check_out_at)),
            'reason' => old('reason', $attendance->remarks),
            'breakRows' => $breakRows,
            'isPlainReadonly' => $readonly && $plainReadonly,
            'readonlyAttr' => $readonly ? 'readonly' : '',
        ];
    }

    private function resolveBreakRows(Collection|EloquentCollection|array $breaks): array
    {

        $oldStarts = old('break_start_at');
        $oldEnds = old('break_end_at');

        if (is_array($oldStarts) || is_array($oldEnds)) {
            $oldStarts = is_array($oldStarts) ? $oldStarts : [];
            $oldEnds = is_array($oldEnds) ? $oldEnds : [];
            $max = max(count($oldStarts), count($oldEnds));
            $rows = [];

            for ($index = 0; $index < $max; $index++) {
                $rows[] = [
                    'start' => $oldStarts[$index] ?? '',
                    'end' => $oldEnds[$index] ?? '',
                ];
            }

            return $rows;
        }

        $rows = [];
        $breakCollection = ($breaks instanceof Collection || $breaks instanceof EloquentCollection) ? $breaks : collect($breaks);
        if ($breakCollection->count() > 0) {
            foreach ($breakCollection as $row) {
                $rows[] = [
                    'start' => $this->formatHm($row->break_start_at),
                    'end' => $this->formatHm($row->break_end_at),
                ];
            }

            return $rows;
        }

        return $rows;
    }

    private function formatHm(CarbonInterface|string|null $dateTime): string
    {

        if (! $dateTime) {
            return '';
        }

        return Carbon::parse($dateTime)->format('H:i');
    }
}
