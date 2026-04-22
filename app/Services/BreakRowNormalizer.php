<?php

namespace App\Services;

use Carbon\Carbon;

class BreakRowNormalizer
{
    public function fromRequest(array $data): array
    {
        // リクエストの休憩開始/終了を共通正規化に渡す。
        return $this->normalizeRows(
            $data['break_start_at'] ?? [],
            $data['break_end_at'] ?? [],
        );
    }

    public function fromCorrections(iterable $breakCorrections): array
    {
        // 開始時刻配列。
        $starts = [];
        // 終了時刻配列。
        $ends = [];

        // 申請休憩を走査して時刻文字列へ統一する。
        foreach ($breakCorrections as $breakRow) {
            $starts[] = $breakRow->break_start_at
                ? Carbon::parse($breakRow->break_start_at)->format('H:i:s')
                : null;
            $ends[] = $breakRow->break_end_at
                ? Carbon::parse($breakRow->break_end_at)->format('H:i:s')
                : null;
        }

        // 抽出した配列を共通正規化に渡す。
        return $this->normalizeRows($starts, $ends);
    }

    public function toDateTimeRows(string $baseDate, array $rows): array
    {
        // DB保存用の配列。
        $dateTimeRows = [];

        // 正規化済み行を日時へ変換する。
        foreach ($rows as $row) {
            $dateTimeRows[] = [
                'break_start_at' => Carbon::parse($baseDate . ' ' . $row['start'])->toDateTimeString(),
                'break_end_at' => ! blank($row['end'])
                    ? Carbon::parse($baseDate . ' ' . $row['end'])->toDateTimeString()
                    : null,
            ];
        }

        // 変換結果を返す。
        return $dateTimeRows;
    }

    private function normalizeRows(array $starts, array $ends): array
    {
        // 長い方の配列長を行数に採用する。
        $rowCount = max(count($starts), count($ends));
        // 正規化後の行配列。
        $rows = [];

        // 1行ずつ正規化する。
        for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
            // 開始時刻。
            $startAt = $starts[$rowIndex] ?? null;
            // 終了時刻。
            $endAt = $ends[$rowIndex] ?? null;

            // 開始が空の行は無効として捨てる。
            if (blank($startAt)) {
                continue;
            }

            // 空文字は null に統一する。
            $rows[] = [
                'start' => $startAt,
                'end' => blank($endAt) ? null : $endAt,
            ];
        }

        // 正規化済み配列を返す。
        return $rows;
    }
}
