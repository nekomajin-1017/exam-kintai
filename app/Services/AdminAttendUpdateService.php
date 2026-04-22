<?php

namespace App\Services;

use Carbon\Carbon;

class AdminAttendUpdateService
{
    public function __construct(private BreakRowNormalizer $breakRowNormalizer)
    {
        // 休憩行の正規化処理を受け取る。
    }

    public function update($attendance, $data)
    {
        // 勤務日を基準日として固定する。
        $baseDate = Carbon::parse($attendance->work_date)->format('Y-m-d');

        // 出退勤時刻と備考を更新する。
        $attendance->update([
            'check_in_at' => ! empty($data['start_time']) ? Carbon::parse($baseDate.' '.$data['start_time']) : null,
            'check_out_at' => ! empty($data['end_time']) ? Carbon::parse($baseDate.' '.$data['end_time']) : null,
            'remarks' => $data['reason'] ?? null,
        ]);

        // 休憩入力を正規化してDB保存形式へ変換する。
        $breakRows = $this->breakRowNormalizer->toDateTimeRows(
            $baseDate,
            $this->breakRowNormalizer->fromRequest($data)
        );

        // 既存休憩を削除して再作成する。
        $attendance->breaks()->delete();

        // 休憩行がある場合のみ作成する。
        if (! empty($breakRows)) {
            $attendance->breaks()->createMany($breakRows);
        }
    }
}
