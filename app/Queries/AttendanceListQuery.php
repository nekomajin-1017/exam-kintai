<?php

namespace App\Queries;

use App\Models\Attendance;
use App\Services\DurationService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AttendanceListQuery
{
    public function __construct(private DurationService $durationService)
    {
        // 勤怠時間計算を委譲するサービス。
    }

    public function forDay(CarbonInterface $date): Collection
    {
        // 指定日の勤怠を取得する（ユーザー/休憩を同時ロード）。
        $records = Attendance::query()
            ->with('user', 'breaks')
            ->whereDate('work_date', $date->toDateString())
            ->orderBy('user_id')
            ->get();

        // 表示用の休憩秒数・労働秒数を付与する。
        $records->each(fn ($attendance) => $this->durationService->attach($attendance));

        // 一覧データを返す。
        return $records;
    }

    public function forUserMonth(
        int $userId,
        CarbonInterface $month,
        bool $withUser = false,
        bool $includeMissingDates = false
    ): Collection {
        // 対象月の開始日。
        $start = Carbon::parse($month)->startOfMonth();
        // 対象月の終了日。
        $end = Carbon::parse($month)->endOfMonth();

        // 呼び出し側の用途で user リレーション読み込みを切り替える。
        $relations = $withUser ? ['user', 'breaks'] : ['breaks'];

        // 指定ユーザーの月次勤怠を取得する。
        $records = Attendance::query()
            ->with($relations)
            ->where('user_id', $userId)
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('work_date')
            ->get();

        // 欠損補完不要なら計算値だけ付与して返す。
        if (! $includeMissingDates) {
            $records->each(fn ($attendance) => $this->durationService->attach($attendance));
            return $records;
        }

        // work_date をキー化して日付参照をO(1)にする。
        $indexed = $records->keyBy(
            fn ($attendance) => Carbon::parse($attendance->work_date)->toDateString()
        );

        // 欠損補完後の結果。
        $filled = collect();
        // 月初から日単位で走査する。
        $cursor = $start->copy();

        // 月末まで1日ずつ埋める。
        while ($cursor->lte($end)) {
            // 当日キー。
            $dateKey = $cursor->toDateString();
            // 当日の実データ。
            $attendance = $indexed->get($dateKey);

            // 欠損日は空行を仮生成する。
            if (! $attendance) {
                $attendance = new Attendance(['user_id' => $userId, 'work_date' => $dateKey]);
                $attendance->setRelation('breaks', collect());
            }

            // 表示用計算値を付与する。
            $this->durationService->attach($attendance);
            // 結果へ追加する。
            $filled->push($attendance);
            // 翌日へ進める。
            $cursor->addDay();
        }

        // 欠損補完済み一覧を返す。
        return $filled;
    }
}
