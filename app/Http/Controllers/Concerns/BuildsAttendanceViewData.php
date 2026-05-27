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
    // 月送りナビゲーション用のURLと表示ラベルを組み立て。
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

    // 日送りナビゲーション用のURLと表示ラベルを組み立て。
    protected function buildDayNavigation(CarbonInterface $day, string $routeName, array $params = []): array
    {
        $current = $day->copy()->startOfDay();
        $previous = $current->copy()->subDay();
        $next = $current->copy()->addDay();

        return [
            'previousUrl' => route($routeName, [...$params, 'date' => $previous->toDateString()]),
            'nextUrl' => route($routeName, [...$params, 'date' => $next->toDateString()]),
            'currentLabel' => $current->locale('ja')->isoFormat('Y年M月D日'),
            'previousLabel' => $previous->locale('ja')->isoFormat('Y年M月D日'),
            'nextLabel' => $next->locale('ja')->isoFormat('Y年M月D日'),
        ];
    }

    // 修正申請の値を反映した詳細表示用データ作成。
    protected function buildDetailFromCorrection(AttendanceCorrection $correction): array
    {
        $correction->load('attendance.user', 'attendance.attendanceBreaks', 'breakCorrections');

        $attendance = $correction->attendance;
        $attendance->check_in_at = $correction->requested_check_in_at ?? $attendance->check_in_at;
        $attendance->check_out_at = $correction->requested_check_out_at ?? $attendance->check_out_at;
        $attendance->remarks = $correction->reason ?? $attendance->remarks;

        $correctionBreakRows = $correction->breakCorrections->sortBy('break_start_at')->values();

        return [
            'attendance' => $attendance,
            'breaks' => $correctionBreakRows,
        ];
    }

    // 勤怠詳細フォームで使う表示項目作成。
    protected function buildAttendanceDetailFields(
        Attendance $attendance,
        Collection|EloquentCollection|array $breaks,
        bool $readonly
    ): array {
        $breakRows = $this->buildBreakRowsForView($breaks);
        if (count($breakRows) === 0) {
            $breakRows[] = ['start' => '', 'end' => ''];
        }

        if (! $readonly) {
            $breakRows[] = ['start' => '', 'end' => ''];
        }

        return [
            'name' => $attendance->user->name,
            'workDateLabel' => Carbon::parse($attendance->work_date)->locale('ja')->isoFormat('Y年M月D日'),
            'startTime' => old('start_time', $this->formatHm($attendance->check_in_at)),
            'endTime' => old('end_time', $this->formatHm($attendance->check_out_at)),
            'reason' => old('reason', $attendance->remarks),
            'breakRows' => $breakRows,
            'isPlainReadonly' => $readonly,
            'readonlyAttr' => $readonly ? 'readonly' : '',
        ];
    }

    // 休憩入力を画面表示用の start/end 行配列に整形。
    private function buildBreakRowsForView(Collection|EloquentCollection|array $breaks): array
    {
        $oldStarts = old('break_start_at');
        $oldEnds = old('break_end_at');

        if (is_array($oldStarts) || is_array($oldEnds)) {
            $oldStarts = is_array($oldStarts) ? $oldStarts : [];
            $oldEnds = is_array($oldEnds) ? $oldEnds : [];
            $max = max(count($oldStarts), count($oldEnds));
            $breakInputRows = [];

            for ($index = 0; $index < $max; $index++) {
                $breakInputRows[] = [
                    'start' => $oldStarts[$index] ?? '',
                    'end' => $oldEnds[$index] ?? '',
                ];
            }

            return $breakInputRows;
        }

        $breakInputRows = [];
        $breakCollection = ($breaks instanceof Collection || $breaks instanceof EloquentCollection) ? $breaks : collect($breaks);
        if ($breakCollection->count() > 0) {
            foreach ($breakCollection as $breakRecord) {
                $breakInputRows[] = [
                    'start' => $this->formatHm($breakRecord->break_start_at),
                    'end' => $this->formatHm($breakRecord->break_end_at),
                ];
            }

            return $breakInputRows;
        }

        return $breakInputRows;
    }

    // 日時を H:i 形式の文字列へ変換し、空なら空文字を返す。
    private function formatHm(CarbonInterface|string|null $dateTime): string
    {
        if (! $dateTime) {
            return '';
        }

        return Carbon::parse($dateTime)->format('H:i');
    }
}
