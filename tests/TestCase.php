<?php

namespace Tests;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    protected function withFrozenTime(string $dateTime, callable $callback): mixed
    {
        $previousNow = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::parse($dateTime, 'Asia/Tokyo'));

        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previousNow);
        }
    }

    protected function loginUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['is_admin' => false], $attributes));
        /** @var User $user */
        $this->actingAs($user);

        return $user;
    }

    protected function loginAdmin(array $attributes = []): User
    {
        $admin = User::factory()->create(array_merge(['is_admin' => true], $attributes));
        /** @var User $admin */
        $this->actingAs($admin);

        return $admin;
    }

    protected function createAttendance(
        User $user,
        string $workDate,
        array $overrides = []
    ): Attendance {
        return Attendance::factory()->for($user)->create(array_merge([
            'work_date' => $workDate,
            'check_in_at' => "{$workDate} 09:00:00",
            'check_out_at' => "{$workDate} 18:00:00",
            'attendance_status_code' => 'finished',
            'remarks' => null,
        ], $overrides));
    }
}
