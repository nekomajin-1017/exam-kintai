<?php

namespace Database\Seeders;

use App\Constants\ApprovalStatusCode;
use App\Constants\AttendanceStatusCode;
use App\Models\Attendance;
use App\Models\AttendanceBreak;
use App\Models\AttendanceCorrection;
use App\Models\BreakCorrection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    private const COMMON_PASSWORD = 'Coachtech777';
    private const ATTENDANCE_DAYS = 30;
    private array $holidayCache = [];

    public function run()
    {
        $users = $this->createUsers();
        $admins = $this->createAdmins();

        $records = $this->createAttendances($users);
        $this->createPendingCorrections($records);
        $this->createApprovedCorrections($records, $admins);
    }

    private function createUsers()
    {
        return collect(range(1, 10))->map(function ($number) {
            return User::factory()->create([
                'name' => "user{$number}",
                'email' => "user{$number}@example.com",
                'password' => Hash::make(self::COMMON_PASSWORD),
                'is_admin' => false,
            ]);
        });
    }

    private function createAdmins()
    {
        return collect(range(1, 3))->map(function ($number) {
            return User::factory()->create([
                'name' => "admin{$number}",
                'email' => "admin{$number}@example.com",
                'password' => Hash::make(self::COMMON_PASSWORD),
                'is_admin' => true,
            ]);
        });
    }

    private function createAttendances($users)
    {
        $all = collect();
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

            $all = $all->concat($attendances);
        }

        return $all;
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
        $add = function (int $month, int $day) use ($year, &$holidays): void {
            $holidays[Carbon::create($year, $month, $day)->toDateString()] = true;
        };
        $addDate = function (Carbon $date) use (&$holidays): void {
            $holidays[$date->toDateString()] = true;
        };

        $add(1, 1); // 元日
        $addDate($this->nthMonday($year, 1, 2)); // 成人の日
        $add(2, 11); // 建国記念の日
        if ($year >= 2020) {
            $add(2, 23); // 天皇誕生日
        }
        $add(3, $this->vernalEquinoxDay($year)); // 春分の日
        $add(4, 29); // 昭和の日
        $add(5, 3); // 憲法記念日
        $add(5, 4); // みどりの日
        $add(5, 5); // こどもの日
        $addDate($this->nthMonday($year, 7, 3)); // 海の日
        $add(8, 11); // 山の日
        $addDate($this->nthMonday($year, 9, 3)); // 敬老の日
        $add(9, $this->autumnalEquinoxDay($year)); // 秋分の日
        $addDate($this->nthMonday($year, 10, 2)); // スポーツの日
        $add(11, 3); // 文化の日
        $add(11, 23); // 勤労感謝の日

        // 国民の休日（祝日に挟まれた平日）
        $startOfYear = Carbon::create($year, 1, 1);
        for ($day = 2; $day < $startOfYear->daysInYear; $day++) {
            $date = $startOfYear->copy()->addDays($day - 1);
            $prev = $date->copy()->subDay()->toDateString();
            $next = $date->copy()->addDay()->toDateString();
            if (! isset($holidays[$date->toDateString()]) && isset($holidays[$prev]) && isset($holidays[$next])) {
                $holidays[$date->toDateString()] = true;
            }
        }

        // 振替休日（祝日が日曜なら翌平日）
        $baseHolidayDates = array_keys($holidays);
        sort($baseHolidayDates);
        foreach ($baseHolidayDates as $holidayDate) {
            $date = Carbon::parse($holidayDate);
            if (! $date->isSunday()) {
                continue;
            }

            $substitute = $date->copy()->addDay();
            while (isset($holidays[$substitute->toDateString()])) {
                $substitute = $substitute->addDay();
            }
            $holidays[$substitute->toDateString()] = true;
        }

        $this->holidayCache[$year] = $holidays;
        return $holidays;
    }

    private function nthMonday(int $year, int $month, int $nth): Carbon
    {
        $date = Carbon::create($year, $month, 1);
        while (! $date->isMonday()) {
            $date->addDay();
        }

        return $date->addWeeks($nth - 1);
    }

    private function vernalEquinoxDay(int $year): int
    {
        return (int) floor(20.8431 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));
    }

    private function autumnalEquinoxDay(int $year): int
    {
        return (int) floor(23.2488 + 0.242194 * ($year - 1980) - floor(($year - 1980) / 4));
    }

    private function createRandomBreaks($attendance)
    {
        if (! $attendance->check_in_at || ! $attendance->check_out_at) return;

        $breakCount = random_int(0, 2);
        $windowStart = $attendance->check_in_at->copy()->addMinutes(60);
        $windowEnd = $attendance->check_out_at->copy()->subMinutes(60);

        for ($index = 0; $index < $breakCount; $index++) {
            $latestStart = $windowEnd->copy()->subMinutes(20);
            if ($windowStart->greaterThanOrEqualTo($latestStart)) break;

            $breakStartAt = Carbon::instance(fake()->dateTimeBetween($windowStart, $latestStart));
            $breakEndAt = $breakStartAt->copy()->addMinutes(random_int(15, 45));
            if ($breakEndAt->greaterThan($windowEnd)) $breakEndAt = $windowEnd->copy();

            AttendanceBreak::create([
                'attendance_id' => $attendance->id,
                'break_start_at' => $breakStartAt,
                'break_end_at' => $breakEndAt,
            ]);

            $windowStart = $breakEndAt->copy()->addMinutes(15);
        }
    }

    private function createPendingCorrections($records)
    {
        foreach ($records->random(min(20, $records->count())) as $attendance) {
            $correction = AttendanceCorrection::factory()->create(
                $this->correctionAttrs($attendance, ApprovalStatusCode::PENDING)
            );
            $this->breakRows($attendance, $correction);
        }
    }

    private function createApprovedCorrections($records, $admins)
    {
        foreach ($records->random(min(20, $records->count())) as $attendance) {
            $correction = AttendanceCorrection::factory()->create(
                $this->correctionAttrs($attendance, ApprovalStatusCode::APPROVED, $admins->random()->id)
            );
            $this->breakRows($attendance, $correction);
        }
    }

    private function correctionAttrs($attendance, string $statusCode, $approvedBy = null)
    {
        return [
            'attendance_id' => $attendance->id,
            'request_user_id' => $attendance->user_id,
            'requested_check_in_at' => $attendance->check_in_at?->copy()->addMinutes(random_int(-30, 30)),
            'requested_check_out_at' => $attendance->check_out_at?->copy()->addMinutes(random_int(-30, 30)),
            'approval_status_code' => $statusCode,
            'approved_by' => $approvedBy,
            'approved_at' => $approvedBy ? now()->subDays(random_int(0, 30)) : null,
            'reason' => '打刻修正申請',
        ];
    }

    private function breakRows($attendance, $correction)
    {
        $baseDate = Carbon::parse($attendance->work_date)->format('Y-m-d');
        foreach ($attendance->breaks()->orderBy('break_start_at')->get() as $break) {
            $startTime = $break->break_start_at?->format('H:i:s');
            if (! $startTime) continue;
            $startAt = Carbon::parse($baseDate.' '.$startTime)->addMinutes(random_int(-10, 10));
            $endAt = $break->break_end_at
                ? Carbon::parse($baseDate.' '.$break->break_end_at->format('H:i:s'))->addMinutes(random_int(-10, 10))
                : null;
            if ($endAt && $endAt->lte($startAt)) {
                $endAt = $startAt->copy()->addMinutes(5);
            }
            BreakCorrection::create([
                'attendance_correction_id' => $correction->id,
                'break_start_at' => $startAt,
                'break_end_at' => $endAt,
            ]);
        }
    }
}
