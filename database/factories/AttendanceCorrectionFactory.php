<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceCorrectionFactory extends Factory
{
    protected $model = AttendanceCorrection::class;

    public function definition()
    {
        $date = $this->faker->dateTimeBetween('-60 days', 'now');
        $checkIn = (clone $date)->setTime(9, 0);
        $checkOut = (clone $date)->setTime(18, 0);
        $reason = $this->faker->randomElement(['申請理由①', '申請理由②', '申請理由③']);

        return [
            'attendance_id' => Attendance::factory(),
            'request_user_id' => User::factory(),
            'requested_check_in_at' => $checkIn,
            'requested_check_out_at' => $checkOut,
            'reason' => $reason,
            'approval_status_code' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
