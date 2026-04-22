<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition()
    {
        $date = $this->faker->dateTimeBetween('-60 days', 'now');
        $checkIn = (clone $date)->setTime(9, rand(0, 30));
        $checkOut = (clone $date)->setTime(18, rand(0, 30));

        return [
            'user_id' => User::factory(),
            'work_date' => $date->format('Y-m-d'),
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'attendance_status_code' => 'finished',
            'remarks' => null,
        ];
    }
}
