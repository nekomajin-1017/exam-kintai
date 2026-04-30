<?php

namespace Database\Seeders;

use App\Constants\AttendanceStatusCode;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    private const ATTENDANCE_DAYS = 240;

    private array $holidayCache = [];

    public function run(): void
    {
        $users = User::query()->where('is_admin', false)->get();
        $workDates = $this->recentBusinessDates(self::ATTENDANCE_DAYS);

        foreach ($users as $user) {
            $attendances = Attendance::factory()
                ->count(self::ATTENDANCE_DAYS)
                ->for($user)
                ->state(['attendance_status_code' => AttendanceStatusCode::FINISHED])
                ->sequence(fn ($sequence) => ['work_date' => $workDates[$sequence->index]])
                ->create();

            foreach ($attendances as $attendance) {
                $this->createRandomBreaks($attendance);
            }
        }
    }

    private function recentBusinessDates(int $count): array
    {
        $dates = [];
        $cursor = now()->startOfDay();

        while (count($dates) < $count) {
            if (! $this->isNonBusinessDay($cursor)) {
                $dates[] = $cursor->toDateString();
            }
            $cursor = $cursor->copy()->subDay();
        }

        return array_reverse($dates);
    }

    private function isNonBusinessDay(Carbon $date): bool
    {
        return $date->isWeekend() || isset($this->japaneseHolidays((int) $date->year)[$date->toDateString()]);
    }

    private function japaneseHolidays(int $year): array
    {
        if (isset($this->holidayCache[$year])) {
            return $this->holidayCache[$year];
        }

        $holidays = [];
        for ($month = 1; $month <= 12; $month++) {
            $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
            $selected = [];

            while (count($selected) < 2) {
                $day = random_int(1, $daysInMonth);
                $date = Carbon::create($year, $month, $day);

                if ($date->isWeekend() || isset($selected[$day])) {
                    continue;
                }

                $selected[$day] = true;
                $holidays[$date->toDateString()] = true;
            }
        }

        $this->holidayCache[$year] = $holidays;

        return $holidays;
    }

    private function createRandomBreaks(Attendance $attendance): void
    {
        if (! $attendance->check_in_at || ! $attendance->check_out_at) {
            return;
        }

        $breakCount = random_int(0, 2);
        $windowStart = $attendance->check_in_at->copy()->addMinutes(60);
        $windowEnd = $attendance->check_out_at->copy()->subMinutes(60);

        for ($index = 0; $index < $breakCount; $index++) {
            $latestStart = $windowEnd->copy()->subMinutes(20);
            if ($windowStart->greaterThanOrEqualTo($latestStart)) {
                break;
            }

            $breakStartAt = Carbon::instance(fake()->dateTimeBetween($windowStart, $latestStart));
            $breakEndAt = $breakStartAt->copy()->addMinutes(random_int(15, 45));
            if ($breakEndAt->greaterThan($windowEnd)) {
                $breakEndAt = $windowEnd->copy();
            }

            AttendanceBreak::create([
                'attendance_id' => $attendance->id,
                'break_start_at' => $breakStartAt,
                'break_end_at' => $breakEndAt,
            ]);

            $windowStart = $breakEndAt->copy()->addMinutes(15);
        }
    }
}
