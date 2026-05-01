<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class DurationService
{
    public static function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public function attach($attendance): void
    {

        $breakSeconds = $this->breakSeconds($attendance);

        $totalSeconds = $this->workSeconds($attendance, $breakSeconds);

        $attendance->setAttribute('calculated_break_seconds', $breakSeconds);

        $attendance->setAttribute('calculated_total_seconds', $totalSeconds);
    }

    public function breakSeconds($attendance): int
    {

        $checkIn = $this->toWorkDate($attendance, $attendance->check_in_at);

        $checkOut = $this->toWorkDate($attendance, $attendance->check_out_at);

        return (int) $attendance->breaks->sum(function ($break) use ($attendance, $checkIn, $checkOut) {

            if (! $break->break_start_at) {
                return 0;
            }

            $start = $this->toWorkDate($attendance, $break->break_start_at);

            $end = $break->break_end_at ? $this->toWorkDate($attendance, $break->break_end_at) : ($checkOut ? $checkOut->copy() : null);

            if (! $start || ! $end || $end->lte($start)) {
                return 0;
            }

            if ($checkIn && $checkOut) {

                $effectiveStart = $start->lt($checkIn) ? $checkIn : $start;

                $effectiveEnd = $end->gt($checkOut) ? $checkOut : $end;

                if ($effectiveEnd->lte($effectiveStart)) {
                    return 0;
                }

                return $effectiveStart->diffInSeconds($effectiveEnd);
            }

            return $start->diffInSeconds($end);
        });
    }

    public function workSeconds($attendance, int $breakSeconds): int
    {

        $checkIn = $this->toWorkDate($attendance, $attendance->check_in_at);

        $checkOut = $this->toWorkDate($attendance, $attendance->check_out_at);

        if (! $checkIn || ! $checkOut || $checkOut->lte($checkIn)) {
            return 0;
        }

        return max(0, $checkIn->diffInSeconds($checkOut) - (int) $breakSeconds);
    }

    private function toWorkDate($attendance, $dateTime): ?CarbonInterface
    {

        if (! $dateTime) {
            return null;
        }

        $baseDate = Carbon::parse($attendance->work_date)->format('Y-m-d');

        $timePart = Carbon::parse($dateTime)->format('H:i:s');

        return Carbon::parse($baseDate.' '.$timePart);
    }
}
