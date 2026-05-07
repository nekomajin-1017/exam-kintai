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
        return $this->buildNavigation(
            $month,
            $routeName,
            $params,
            'month',
            'Y-m',
            'Y年M月',
            fn (CarbonInterface $date) => $date->copy()->startOfMonth(),
            fn (CarbonInterface $date) => $date->copy()->subMonth(),
            fn (CarbonInterface $date) => $date->copy()->addMonth(),
        );
    }

    // 日送りナビゲーション用のURLと表示ラベルを組み立て。
    protected function buildDayNavigation(CarbonInterface $day, string $routeName, array $params = []): array
    {
        return $this->buildNavigation(
            $day,
            $routeName,
            $params,
            'date',
            'Y-m-d',
            'Y年MM月DD日',
            fn (CarbonInterface $date) => $date->copy()->startOfDay(),
            fn (CarbonInterface $date) => $date->copy()->subDay(),
            fn (CarbonInterface $date) => $date->copy()->addDay(),
        );
    }

    // 修正申請の値を反映した詳細表示用データを作成。
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

    // 勤怠詳細フォームで使う表示項目を作成。
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

    // 休憩入力を画面表示用の start/end 行配列に整形。
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

    // 日時を H:i 形式の文字列へ変換し、空なら空文字を返す。
    private function formatHm(CarbonInterface|string|null $dateTime): string
    {

        if (! $dateTime) {
            return '';
        }

        return Carbon::parse($dateTime)->format('H:i');
    }

    // 前後ナビゲーションのURLと表示ラベルを共通生成。
    private function buildNavigation(
        CarbonInterface $base,
        string $routeName,
        array $params,
        string $queryKey,
        string $queryFormat,
        string $labelFormat,
        callable $normalize,
        callable $previousResolver,
        callable $nextResolver
    ): array {

        $current = $normalize($base);
        $previous = $previousResolver($current);
        $next = $nextResolver($current);

        return [
            'previousUrl' => route($routeName, [...$params, $queryKey => $previous->format($queryFormat)]),
            'nextUrl' => route($routeName, [...$params, $queryKey => $next->format($queryFormat)]),
            'currentLabel' => $current->locale('ja')->isoFormat($labelFormat),
            'previousLabel' => $previous->locale('ja')->isoFormat($labelFormat),
            'nextLabel' => $next->locale('ja')->isoFormat($labelFormat),
        ];
    }
}
